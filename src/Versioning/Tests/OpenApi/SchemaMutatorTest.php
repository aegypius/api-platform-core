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

namespace ApiPlatform\Versioning\Tests\OpenApi;

use ApiPlatform\Versioning\Metadata\MutatorMetadataFactory;
use ApiPlatform\Versioning\OpenApi\SchemaMutator;
use ApiPlatform\Versioning\State\DowngradeChainResolver;
use ApiPlatform\Versioning\Tests\Fixtures\Book;
use ApiPlatform\Versioning\Tests\Fixtures\BookBananaToApple;
use ApiPlatform\Versioning\Tests\Fixtures\BookCherryToBanana;
use ApiPlatform\Versioning\Tests\Fixtures\DropInternalNotes;
use ApiPlatform\Versioning\Version\VersionGraph;
use PHPUnit\Framework\TestCase;

final class SchemaMutatorTest extends TestCase
{
    /**
     * @return list<\ApiPlatform\Versioning\Metadata\BoundMutation>
     */
    private function chain(string $target): array
    {
        $registry = (new MutatorMetadataFactory())->create([
            BookCherryToBanana::class,
            BookBananaToApple::class,
            DropInternalNotes::class,
        ]);
        $graph = VersionGraph::fromSteps($registry->getSteps(), 'cherry');

        return (new DowngradeChainResolver($graph, $registry))->resolve(Book::class, $target);
    }

    /**
     * @return array<string, mixed>
     */
    private function headSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string'],
                'discount' => ['type' => 'integer'],
                'internalNotes' => ['type' => 'string'],
                'lastUpdated' => ['type' => 'string', 'format' => 'date-time'],
                'available' => ['type' => 'boolean'],
                'isbn' => ['type' => 'string'],
            ],
            'required' => ['title', 'isbn'],
        ];
    }

    public function testDowngradedSchemaShape(): void
    {
        $result = (new SchemaMutator())->mutate($this->headSchema(), $this->chain('apple'));

        // discount + internalNotes removed; title -> name; lastUpdated -> updatedAt;
        // available boolean -> integer.
        $this->assertArrayNotHasKey('discount', $result['properties']);
        $this->assertArrayNotHasKey('internalNotes', $result['properties']);
        $this->assertArrayNotHasKey('title', $result['properties']);
        $this->assertArrayNotHasKey('lastUpdated', $result['properties']);

        $this->assertSame(['type' => 'string'], $result['properties']['name']);
        $this->assertSame(['type' => 'string', 'format' => 'date-time'], $result['properties']['updatedAt']);
        $this->assertSame(['type' => 'integer'], $result['properties']['available']);
        $this->assertSame(['type' => 'string'], $result['properties']['isbn']);

        // required: title renamed to name; isbn kept.
        $this->assertSame(['name', 'isbn'], $result['required']);
    }
}
