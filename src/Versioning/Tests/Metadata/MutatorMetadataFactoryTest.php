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

namespace ApiPlatform\Versioning\Tests\Metadata;

use ApiPlatform\Versioning\Attributes\ChangeType;
use ApiPlatform\Versioning\Attributes\Remove;
use ApiPlatform\Versioning\Attributes\Rename;
use ApiPlatform\Versioning\Metadata\MutatorMetadataFactory;
use ApiPlatform\Versioning\Metadata\MutatorRegistry;
use ApiPlatform\Versioning\Tests\Fixtures\Book;
use ApiPlatform\Versioning\Tests\Fixtures\BookBananaToApple;
use ApiPlatform\Versioning\Tests\Fixtures\BookCherryToBanana;
use ApiPlatform\Versioning\Tests\Fixtures\DropInternalNotes;
use ApiPlatform\Versioning\Tests\Fixtures\Review;
use PHPUnit\Framework\TestCase;

final class MutatorMetadataFactoryTest extends TestCase
{
    private function registry(): MutatorRegistry
    {
        return (new MutatorMetadataFactory())->create([
            BookCherryToBanana::class,
            BookBananaToApple::class,
            DropInternalNotes::class,
        ]);
    }

    public function testDiscoversResources(): void
    {
        $resources = $this->registry()->getResources();
        sort($resources);
        $this->assertSame([Book::class, Review::class], $resources);
    }

    public function testDistinctForVersionsAreDeduplicated(): void
    {
        $forVersions = $this->registry()->getForVersions();
        sort($forVersions);
        // "banana" contributed by Book (two classes) and Review; "apple" by Book.
        $this->assertSame(['apple', 'banana'], $forVersions);
    }

    public function testClassLevelMutationsAreCollectedAcrossClasses(): void
    {
        $mutations = $this->registry()->getMutations(Book::class, 'banana');

        $this->assertCount(3, $mutations);
        foreach ($mutations as $m) {
            $this->assertNull($m->method, 'class-level mutations have no bound method');
        }

        $kinds = array_map(static fn ($m): string => $m->mutation::class, $mutations);
        $this->assertSame([Remove::class, Rename::class, Remove::class], $kinds);

        $this->assertInstanceOf(Remove::class, $mutations[0]->mutation);
        $this->assertSame('discount', $mutations[0]->mutation->property);

        $this->assertInstanceOf(Remove::class, $mutations[2]->mutation);
        $this->assertSame('internalNotes', $mutations[2]->mutation->property);
    }

    public function testRepeatableBindingAppliesMutationsToEachResource(): void
    {
        $mutations = $this->registry()->getMutations(Review::class, 'banana');
        $this->assertCount(1, $mutations);
        $this->assertInstanceOf(Remove::class, $mutations[0]->mutation);
        $this->assertSame('internalNotes', $mutations[0]->mutation->property);
    }

    public function testMethodLevelMutationsBindTheMethodName(): void
    {
        $mutations = $this->registry()->getMutations(Book::class, 'apple');
        $this->assertCount(2, $mutations);

        $this->assertInstanceOf(Rename::class, $mutations[0]->mutation);
        $this->assertSame('downgradeUpdatedAt', $mutations[0]->method);
        $this->assertSame(BookBananaToApple::class, $mutations[0]->mutatorClass);

        $this->assertInstanceOf(ChangeType::class, $mutations[1]->mutation);
        $this->assertSame('downgradeAvailable', $mutations[1]->method);
    }

    public function testUnknownVersionReturnsNoMutations(): void
    {
        $this->assertSame([], $this->registry()->getMutations(Book::class, 'cherry'));
    }
}
