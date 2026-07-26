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

namespace ApiPlatform\Versioning\Tests\Serializer;

use ApiPlatform\Versioning\Metadata\MutatorMetadataFactory;
use ApiPlatform\Versioning\Serializer\VersionMutationNormalizer;
use ApiPlatform\Versioning\State\DowngradeChainResolver;
use ApiPlatform\Versioning\State\ResponseMutator;
use ApiPlatform\Versioning\Tests\Fixtures\Book;
use ApiPlatform\Versioning\Tests\Fixtures\BookBananaToApple;
use ApiPlatform\Versioning\Tests\Fixtures\BookCherryToBanana;
use ApiPlatform\Versioning\Tests\Fixtures\DropInternalNotes;
use ApiPlatform\Versioning\Tests\Fixtures\Review;
use ApiPlatform\Versioning\Tests\Fixtures\FruitComparator;
use ApiPlatform\Versioning\Version\VersionGraph;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class VersionMutationNormalizerTest extends TestCase
{
    /**
     * @param array<string, mixed> $itemArray
     */
    private function normalizer(array $itemArray): VersionMutationNormalizer
    {
        $decorated = new class($itemArray) implements NormalizerInterface {
            /** @param array<string, mixed> $itemArray */
            public function __construct(private readonly array $itemArray)
            {
            }

            public function normalize(mixed $data, ?string $format = null, array $context = []): array
            {
                return $this->itemArray;
            }

            public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
            {
                return $data instanceof Book || $data instanceof Review;
            }

            public function getSupportedTypes(?string $format): array
            {
                return [Book::class => true, Review::class => true];
            }
        };

        $registry = (new MutatorMetadataFactory())->create([
            BookCherryToBanana::class,
            BookBananaToApple::class,
            DropInternalNotes::class,
        ]);
        $graph = VersionGraph::fromVersions($registry->getForVersions(), 'cherry', new FruitComparator());

        return new VersionMutationNormalizer(
            $decorated,
            new DowngradeChainResolver($graph, $registry),
            new ResponseMutator(),
            $graph,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function sampleBook(): array
    {
        return [
            'title' => 'Foo',
            'discount' => 10,
            'internalNotes' => 'secret',
            'lastUpdated' => '2026-01-02T03:04:05+02:00',
            'available' => true,
            'isbn' => '123',
        ];
    }

    public function testDowngradesItemToRequestedVersion(): void
    {
        $result = $this->normalizer($this->sampleBook())->normalize(
            new Book(),
            'jsonld',
            [VersionMutationNormalizer::VERSION_CONTEXT_KEY => 'apple'],
        );

        $this->assertEqualsCanonicalizing([
            'name' => 'Foo',
            'updatedAt' => '2026-01-02T03:04:05',
            'available' => 1,
            'isbn' => '123',
        ], $result);
    }

    public function testNoVersionInContextPassesThrough(): void
    {
        $book = $this->sampleBook();
        $this->assertSame($book, $this->normalizer($book)->normalize(new Book(), 'jsonld', []));
    }

    public function testHeadVersionPassesThrough(): void
    {
        $book = $this->sampleBook();
        $result = $this->normalizer($book)->normalize(
            new Book(),
            'jsonld',
            [VersionMutationNormalizer::VERSION_CONTEXT_KEY => 'cherry'],
        );
        $this->assertSame($book, $result);
    }

    public function testSparseResourceOnlyAppliesItsOwnMutations(): void
    {
        $result = $this->normalizer(['internalNotes' => 'x', 'stars' => 5])->normalize(
            new Review(),
            'jsonld',
            [VersionMutationNormalizer::VERSION_CONTEXT_KEY => 'apple'],
        );
        $this->assertSame(['stars' => 5], $result);
    }

    public function testSupportsNormalizationDelegatesToDecorated(): void
    {
        $normalizer = $this->normalizer([]);
        $this->assertTrue($normalizer->supportsNormalization(new Book(), 'jsonld'));
        $this->assertFalse($normalizer->supportsNormalization(new \stdClass(), 'jsonld'));
    }
}
