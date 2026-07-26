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

use ApiPlatform\Versioning\Attributes\ChangeType;
use ApiPlatform\Versioning\Attributes\Remove;
use ApiPlatform\Versioning\Attributes\Rename;
use ApiPlatform\Versioning\Metadata\MutatorMetadataFactory;
use ApiPlatform\Versioning\State\DowngradeChainResolver;
use ApiPlatform\Versioning\Tests\Fixtures\Book;
use ApiPlatform\Versioning\Tests\Fixtures\BookBananaToApple;
use ApiPlatform\Versioning\Tests\Fixtures\BookCherryToBanana;
use ApiPlatform\Versioning\Tests\Fixtures\DropInternalNotes;
use ApiPlatform\Versioning\Tests\Fixtures\Review;
use ApiPlatform\Versioning\Version\VersionGraph;
use PHPUnit\Framework\TestCase;

final class DowngradeChainResolverTest extends TestCase
{
    private function resolver(): DowngradeChainResolver
    {
        $registry = (new MutatorMetadataFactory())->create([
            BookCherryToBanana::class,
            BookBananaToApple::class,
            DropInternalNotes::class,
        ]);
        $graph = VersionGraph::fromSteps($registry->getSteps(), 'cherry');

        return new DowngradeChainResolver($graph, $registry);
    }

    public function testResolvesFullChainNewestToOldest(): void
    {
        $chain = $this->resolver()->resolve(Book::class, 'apple');

        $kinds = array_map(static fn ($m): string => $m->mutation::class, $chain);
        $this->assertSame([
            Remove::class,      // discount        (cherry>banana)
            Rename::class,      // title>name      (cherry>banana)
            Remove::class,      // internalNotes   (cherry>banana)
            Rename::class,      // lastUpdated     (banana>apple, method)
            ChangeType::class,  // available       (banana>apple, method)
        ], $kinds);
        $this->assertSame('downgradeUpdatedAt', $chain[3]->method);
    }

    public function testResolvesPartialChain(): void
    {
        $chain = $this->resolver()->resolve(Book::class, 'banana');
        $this->assertCount(3, $chain);
    }

    public function testHeadResolvesToEmptyChain(): void
    {
        $this->assertSame([], $this->resolver()->resolve(Book::class, 'cherry'));
    }

    public function testSparseResourceSkipsStepsWithoutMutations(): void
    {
        // Review only changed at cherry>banana.
        $chain = $this->resolver()->resolve(Review::class, 'apple');
        $this->assertCount(1, $chain);
        $this->assertInstanceOf(Remove::class, $chain[0]->mutation);
    }
}
