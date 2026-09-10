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
use ApiPlatform\Versioning\OpenApi\ChangelogFactory;
use ApiPlatform\Versioning\OpenApi\DocumentMutator;
use ApiPlatform\Versioning\OpenApi\SchemaMutator;
use ApiPlatform\Versioning\OpenApi\ShortNameSchemaNameResolver;
use ApiPlatform\Versioning\State\DowngradeChainResolver;
use ApiPlatform\Versioning\Tests\Fixtures\BookBananaToApple;
use ApiPlatform\Versioning\Tests\Fixtures\BookCherryToBanana;
use ApiPlatform\Versioning\Tests\Fixtures\DropInternalNotes;
use ApiPlatform\Versioning\Tests\Fixtures\FruitComparator;
use ApiPlatform\Versioning\Version\VersionGraph;
use PHPUnit\Framework\TestCase;

final class DocumentMutatorTest extends TestCase
{
    private function mutator(): DocumentMutator
    {
        $registry = (new MutatorMetadataFactory())->create([
            BookCherryToBanana::class,
            BookBananaToApple::class,
            DropInternalNotes::class,
        ]);
        $graph = VersionGraph::fromVersions($registry->getForVersions(), 'cherry', new FruitComparator());

        return new DocumentMutator(
            $graph,
            $registry,
            new DowngradeChainResolver($graph, $registry),
            new SchemaMutator(),
            new ShortNameSchemaNameResolver(),
            new ChangelogFactory($graph, $registry),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function headDocument(): array
    {
        return [
            'openapi' => '3.1.0',
            'info' => ['title' => 'Bookshop', 'version' => 'cherry'],
            'components' => [
                'schemas' => [
                    'Book' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => ['type' => 'string'],
                            'discount' => ['type' => 'integer'],
                            'internalNotes' => ['type' => 'string'],
                            'lastUpdated' => ['type' => 'string', 'format' => 'date-time'],
                            'available' => ['type' => 'boolean'],
                        ],
                        'required' => ['title'],
                    ],
                    'Book.jsonld' => [
                        'allOf' => [
                            ['$ref' => '#/components/schemas/HydraItemBaseSchema'],
                            [
                                'type' => 'object',
                                'properties' => ['title' => ['type' => 'string'], 'discount' => ['type' => 'integer']],
                            ],
                        ],
                    ],
                    'Author' => [
                        'type' => 'object',
                        'properties' => ['name' => ['type' => 'string']],
                    ],
                ],
            ],
        ];
    }

    public function testMutatesEveryOwnedSchemaAndSetsVersionMetadata(): void
    {
        $document = $this->mutator()->mutate($this->headDocument(), 'apple');

        $book = $document['components']['schemas']['Book']['properties'];
        $this->assertArrayNotHasKey('discount', $book);
        $this->assertArrayNotHasKey('internalNotes', $book);
        $this->assertArrayNotHasKey('title', $book);
        $this->assertArrayNotHasKey('lastUpdated', $book);
        $this->assertSame(['type' => 'string'], $book['name']);
        $this->assertSame(['type' => 'string', 'format' => 'date-time'], $book['updatedAt']);
        $this->assertSame(['type' => 'integer'], $book['available']);

        // every schema the resource owns is mutated, including format variants
        // whose properties are nested under allOf (JSON-LD/Hydra composition).
        $jsonLdProperties = $document['components']['schemas']['Book.jsonld']['allOf'][1]['properties'];
        $this->assertArrayHasKey('name', $jsonLdProperties);
        $this->assertArrayNotHasKey('discount', $jsonLdProperties);

        $this->assertSame('apple', $document['info']['version']);
        $this->assertSame(['cherry', 'banana', 'apple'], $document['info']['x-api-versions']);

        // changelog derived from the mutators is attached alongside x-api-versions.
        $this->assertNotEmpty($document['info']['x-api-changelog']);
        $this->assertContains('Book: removed "discount"', $document['info']['x-api-changelog'][0]['changes']);
    }

    public function testSchemasWithoutMutatorsAreUntouched(): void
    {
        $document = $this->mutator()->mutate($this->headDocument(), 'apple');
        $this->assertSame(['name' => ['type' => 'string']], $document['components']['schemas']['Author']['properties']);
    }

    public function testHeadTargetLeavesSchemasUnchanged(): void
    {
        $document = $this->mutator()->mutate($this->headDocument(), 'cherry');
        $this->assertArrayHasKey('discount', $document['components']['schemas']['Book']['properties']);
        $this->assertSame('cherry', $document['info']['version']);
    }
}
