<?php

/*
 * This file is part of the API Platform project.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ApiPlatform\Versioning\Tests\State;

use ApiPlatform\Versioning\Attributes\Remove;
use ApiPlatform\Versioning\Attributes\Rename;
use ApiPlatform\Versioning\Attributes\Restore;
use ApiPlatform\Versioning\Metadata\BoundMutation;
use ApiPlatform\Versioning\Metadata\MutatorMetadataFactory;
use ApiPlatform\Versioning\State\DowngradeChainResolver;
use ApiPlatform\Versioning\State\ResponseMutator;
use ApiPlatform\Versioning\Tests\Fixtures\Book;
use ApiPlatform\Versioning\Tests\Fixtures\BookBananaToApple;
use ApiPlatform\Versioning\Tests\Fixtures\BookCherryToBanana;
use ApiPlatform\Versioning\Tests\Fixtures\DropInternalNotes;
use ApiPlatform\Versioning\Version\VersionGraph;
use PHPUnit\Framework\TestCase;

final class ResponseMutatorTest extends TestCase
{
    private function mutator(): ResponseMutator
    {
        return new ResponseMutator();
    }

    /**
     * @param list<BoundMutation> $chain
     */
    private function chain(string $resource, string $target): array
    {
        $registry = (new MutatorMetadataFactory())->create([
            BookCherryToBanana::class,
            BookBananaToApple::class,
            DropInternalNotes::class,
        ]);
        $graph = VersionGraph::fromSteps($registry->getSteps(), 'cherry');

        return (new DowngradeChainResolver($graph, $registry))->resolve($resource, $target);
    }

    public function testAppliesFullChain(): void
    {
        $data = [
            'title' => 'Foo',
            'discount' => 10,
            'internalNotes' => 'secret',
            'lastUpdated' => '2026-01-02T03:04:05+02:00',
            'available' => true,
            'isbn' => '123',
        ];

        $result = $this->mutator()->mutate($data, $this->chain(Book::class, 'apple'), []);

        $this->assertEqualsCanonicalizing([
            'name' => 'Foo',                     // title renamed
            'updatedAt' => '2026-01-02T03:04:05', // lastUpdated renamed + tz stripped
            'available' => 1,                    // bool -> int
            'isbn' => '123',                     // untouched
        ], $result);
    }

    public function testEmptyChainIsIdentity(): void
    {
        $data = ['a' => 1];
        $this->assertSame($data, $this->mutator()->mutate($data, [], []));
    }

    public function testClassLevelRenameIsPureKeySwap(): void
    {
        $chain = [new BoundMutation(new Rename(from: 'title', to: 'name'), 'X')];
        $this->assertSame(['name' => 'Foo'], $this->mutator()->mutate(['title' => 'Foo'], $chain, []));
    }

    public function testRemoveDropsProperty(): void
    {
        $chain = [new BoundMutation(new Remove('discount'), 'X')];
        $this->assertSame(['a' => 1], $this->mutator()->mutate(['a' => 1, 'discount' => 9], $chain, []));
    }

    public function testClassLevelRestoreInjectsStaticValue(): void
    {
        $chain = [new BoundMutation(new Restore(property: 'legacyFlag', value: 0), 'X')];
        $this->assertSame(['a' => 1, 'legacyFlag' => 0], $this->mutator()->mutate(['a' => 1], $chain, []));
    }

    public function testContextIsPassedToBoundMethod(): void
    {
        $data = ['available' => true];
        $chain = $this->chain(Book::class, 'apple');
        // keep only the ChangeType (available) mutation
        $chain = array_values(array_filter($chain, static fn ($m): bool => 'downgradeAvailable' === $m->method));

        $result = $this->mutator()->mutate($data, $chain, ['fromVersion' => 'banana', 'toVersion' => 'apple']);
        $this->assertSame(['available' => 1], $result);
    }
}
