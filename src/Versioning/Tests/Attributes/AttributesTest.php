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

namespace ApiPlatform\Versioning\Tests\Attributes;

use ApiPlatform\Versioning\Attributes\ChangeType;
use ApiPlatform\Versioning\Attributes\Remove;
use ApiPlatform\Versioning\Attributes\Rename;
use ApiPlatform\Versioning\Attributes\Restore;
use ApiPlatform\Versioning\Attributes\VersionMutationInterface;
use ApiPlatform\Versioning\Attributes\VersionMutator;
use ApiPlatform\Versioning\Tests\Fixtures\Book;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AttributesTest extends TestCase
{
    public function testVersionMutatorHoldsBinding(): void
    {
        $m = new VersionMutator(resource: Book::class, for: 'banana');
        $this->assertSame(Book::class, $m->resource);
        $this->assertSame('banana', $m->for);
    }

    public function testMutationsAreTaggedAsVersionMutation(): void
    {
        $this->assertInstanceOf(VersionMutationInterface::class, new Remove('discount'));
        $this->assertInstanceOf(VersionMutationInterface::class, new Rename(from: 'title', to: 'name'));
        $this->assertInstanceOf(VersionMutationInterface::class, new ChangeType(property: 'active', from: 'boolean', to: 'integer'));
        $this->assertInstanceOf(VersionMutationInterface::class, new Restore(property: 'legacy', value: 0));
    }

    /**
     * @return iterable<string, array{class-string, int}>
     */
    public static function attributeTargets(): iterable
    {
        yield 'VersionMutator is class + repeatable' => [VersionMutator::class, \Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE];
        yield 'Remove is class-only + repeatable' => [Remove::class, \Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE];
        yield 'Rename is class or method + repeatable' => [Rename::class, \Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE];
        yield 'ChangeType is class or method + repeatable' => [ChangeType::class, \Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE];
        yield 'Restore is class or method + repeatable' => [Restore::class, \Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE];
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('attributeTargets')]
    public function testAttributeTargets(string $class, int $expectedFlags): void
    {
        $reflection = new \ReflectionClass($class);
        $attributes = $reflection->getAttributes(\Attribute::class);
        $this->assertCount(1, $attributes, \sprintf('%s must declare #[\Attribute].', $class));
        $this->assertSame($expectedFlags, $attributes[0]->newInstance()->flags);
    }
}
