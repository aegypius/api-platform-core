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

use ApiPlatform\Versioning\Attributes\Restore;
use ApiPlatform\Versioning\Metadata\BoundMutation;
use ApiPlatform\Versioning\Metadata\MutatorMetadataFactory;
use ApiPlatform\Versioning\OpenApi\SchemaMutator;
use ApiPlatform\Versioning\State\DowngradeChainResolver;
use ApiPlatform\Versioning\Tests\Fixtures\Book;
use ApiPlatform\Versioning\Tests\Fixtures\BookBananaToApple;
use ApiPlatform\Versioning\Tests\Fixtures\BookCherryToBanana;
use ApiPlatform\Versioning\Tests\Fixtures\DropInternalNotes;
use ApiPlatform\Versioning\Tests\Fixtures\FruitComparator;
use ApiPlatform\Versioning\Version\VersionGraph;
use PHPUnit\Framework\TestCase;

final class SchemaMutatorTest extends TestCase
{
    /**
     * @return list<BoundMutation>
     */
    private function chain(string $target): array
    {
        $registry = (new MutatorMetadataFactory())->create([
            BookCherryToBanana::class,
            BookBananaToApple::class,
            DropInternalNotes::class,
        ]);
        $graph = VersionGraph::fromVersions($registry->getForVersions(), 'cherry', new FruitComparator());

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

    /**
     * @return array<string, mixed>
     */
    private function headJsonLdSchema(): array
    {
        return [
            'allOf' => [
                ['$ref' => '#/components/schemas/HydraItemBaseSchema'],
                [
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
                ],
            ],
        ];
    }

    public function testDowngradedSchemaShapeForAJsonLdAllOfComposedSchema(): void
    {
        $result = (new SchemaMutator())->mutate($this->headJsonLdSchema(), $this->chain('apple'));

        // The $ref entry is left untouched; the resource's own properties
        // entry (allOf[1]) is the one that gets mutated.
        $this->assertSame(['$ref' => '#/components/schemas/HydraItemBaseSchema'], $result['allOf'][0]);
        $this->assertArrayNotHasKey('properties', $result, 'properties must not leak to the schema root');

        $properties = $result['allOf'][1]['properties'];
        $this->assertArrayNotHasKey('discount', $properties);
        $this->assertArrayNotHasKey('internalNotes', $properties);
        $this->assertArrayNotHasKey('title', $properties);
        $this->assertArrayNotHasKey('lastUpdated', $properties);

        $this->assertSame(['type' => 'string'], $properties['name']);
        $this->assertSame(['type' => 'string', 'format' => 'date-time'], $properties['updatedAt']);
        $this->assertSame(['type' => 'integer'], $properties['available']);
        $this->assertSame(['type' => 'string'], $properties['isbn']);

        $this->assertSame(['name', 'isbn'], $result['allOf'][1]['required']);
    }

    public function testAllOfWithoutAnyPropertiesEntryIsLeftAloneWhenMutationHasNothingToRestore(): void
    {
        $schema = [
            'allOf' => [
                ['$ref' => '#/components/schemas/HydraItemBaseSchema'],
            ],
        ];

        // "apple" only removes/renames/retypes properties for Book; none of
        // that can create a properties entry out of thin air.
        $result = (new SchemaMutator())->mutate($schema, $this->chain('apple'));

        $this->assertSame($schema, $result);
    }

    public function testAllOfWithoutAnyPropertiesEntryGetsOneAppendedWhenMutationRestoresAProperty(): void
    {
        $schema = [
            'allOf' => [
                ['$ref' => '#/components/schemas/HydraItemBaseSchema'],
            ],
        ];
        $chain = [new BoundMutation(new Restore('internalNotes', 'n/a'), Book::class)];

        $result = (new SchemaMutator())->mutate($schema, $chain);

        $this->assertSame(['$ref' => '#/components/schemas/HydraItemBaseSchema'], $result['allOf'][0]);
        $this->assertSame(
            ['type' => 'object', 'properties' => ['internalNotes' => ['type' => 'string']]],
            $result['allOf'][1],
        );
    }
}
