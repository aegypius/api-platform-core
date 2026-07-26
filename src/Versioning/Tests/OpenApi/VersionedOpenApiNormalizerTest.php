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

use ApiPlatform\Versioning\Exception\OutOfRangeVersionException;
use ApiPlatform\Versioning\Metadata\MutatorMetadataFactory;
use ApiPlatform\Versioning\OpenApi\ChangelogFactory;
use ApiPlatform\Versioning\OpenApi\DocumentMutator;
use ApiPlatform\Versioning\OpenApi\OverlayFactory;
use ApiPlatform\Versioning\OpenApi\SchemaMutator;
use ApiPlatform\Versioning\OpenApi\ShortNameSchemaNameResolver;
use ApiPlatform\Versioning\OpenApi\VersionedOpenApiNormalizer;
use ApiPlatform\Versioning\Serializer\VersionMutationNormalizer;
use ApiPlatform\Versioning\State\DowngradeChainResolver;
use ApiPlatform\Versioning\Tests\Fixtures\BookBananaToApple;
use ApiPlatform\Versioning\Tests\Fixtures\BookCherryToBanana;
use ApiPlatform\Versioning\Tests\Fixtures\DropInternalNotes;
use ApiPlatform\Versioning\Tests\Fixtures\FruitComparator;
use ApiPlatform\Versioning\Version\VersionGraph;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class VersionedOpenApiNormalizerTest extends TestCase
{
    private function normalizer(): VersionedOpenApiNormalizer
    {
        $decorated = new class($this->headDocument()) implements NormalizerInterface {
            /** @param array<string, mixed> $doc */
            public function __construct(private readonly array $doc)
            {
            }

            public function normalize(mixed $data, ?string $format = null, array $context = []): array
            {
                return $this->doc;
            }

            public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
            {
                return true;
            }

            public function getSupportedTypes(?string $format): array
            {
                return ['*' => true];
            }
        };

        $registry = (new MutatorMetadataFactory())->create([
            BookCherryToBanana::class,
            BookBananaToApple::class,
            DropInternalNotes::class,
        ]);
        $graph = VersionGraph::fromVersions($registry->getForVersions(), 'cherry', new FruitComparator());
        $resolver = new DowngradeChainResolver($graph, $registry);

        return new VersionedOpenApiNormalizer(
            $decorated,
            $graph,
            new DocumentMutator($graph, $registry, $resolver, new SchemaMutator(), new ShortNameSchemaNameResolver(), new ChangelogFactory($graph, $registry)),
            new OverlayFactory(),
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
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    private function normalize(array $context): mixed
    {
        return $this->normalizer()->normalize(new \stdClass(), 'json', $context);
    }

    public function testNoVersionPassesThrough(): void
    {
        $this->assertSame($this->headDocument(), $this->normalize([]));
    }

    public function testDowngradesTheDocumentForARequestedVersion(): void
    {
        $doc = $this->normalize([VersionMutationNormalizer::VERSION_CONTEXT_KEY => 'apple']);

        $this->assertArrayNotHasKey('discount', $doc['components']['schemas']['Book']['properties']);
        $this->assertArrayHasKey('name', $doc['components']['schemas']['Book']['properties']);
        $this->assertSame('apple', $doc['info']['version']);
        $this->assertSame(['cherry', 'banana', 'apple'], $doc['info']['x-api-versions']);
    }

    public function testHeadVersionAnnotatesWithoutMutatingSchemas(): void
    {
        $doc = $this->normalize([VersionMutationNormalizer::VERSION_CONTEXT_KEY => 'cherry']);

        // schemas untouched, but the version metadata is published on head too.
        $this->assertArrayHasKey('discount', $doc['components']['schemas']['Book']['properties']);
        $this->assertSame('cherry', $doc['info']['version']);
        $this->assertNotEmpty($doc['info']['x-api-versions']);
    }

    public function testUnknownVersionIsRejected(): void
    {
        $this->expectException(OutOfRangeVersionException::class);
        $this->normalize([VersionMutationNormalizer::VERSION_CONTEXT_KEY => 'durian']);
    }

    public function testEmitsOverlayWhenRequested(): void
    {
        $overlay = $this->normalize([
            VersionMutationNormalizer::VERSION_CONTEXT_KEY => 'apple',
            VersionedOpenApiNormalizer::OVERLAY_CONTEXT_KEY => true,
        ]);

        $this->assertSame('1.0.0', $overlay['overlay']);
        $this->assertSame('apple', $overlay['info']['version']);
        $this->assertNotEmpty($overlay['actions']);
        $this->assertContains(
            ['target' => "$.components.schemas['Book'].properties['discount']", 'remove' => true],
            $overlay['actions'],
        );
    }
}
