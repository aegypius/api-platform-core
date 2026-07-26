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

namespace ApiPlatform\Versioning\Tests\Symfony\State;

use ApiPlatform\State\SerializerContextBuilderInterface;
use ApiPlatform\Versioning\Serializer\VersionMutationNormalizer;
use ApiPlatform\Versioning\State\VersionNegotiator;
use ApiPlatform\Versioning\State\VersionResolverInterface;
use ApiPlatform\Versioning\Symfony\State\VersionSerializerContextBuilder;
use ApiPlatform\Versioning\Version\VersionGraph;
use ApiPlatform\Versioning\Version\VersionStep;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class VersionSerializerContextBuilderTest extends TestCase
{
    private function builder(?string $resolved): VersionSerializerContextBuilder
    {
        $inner = new class implements SerializerContextBuilderInterface {
            public function createFromRequest(Request $request, bool $normalization, ?array $extractedAttributes = null): array
            {
                return ['groups' => ['book:read']];
            }
        };

        $resolver = new class($resolved) implements VersionResolverInterface {
            public function __construct(private readonly ?string $resolved)
            {
            }

            public function resolve(Request $request): ?string
            {
                return $this->resolved;
            }

            public function getVary(): ?string
            {
                return 'Accept-Version';
            }
        };

        $graph = VersionGraph::fromSteps([
            new VersionStep('cherry', 'banana'),
            new VersionStep('banana', 'apple'),
        ], 'cherry');

        return new VersionSerializerContextBuilder($inner, $resolver, new VersionNegotiator(), $graph);
    }

    public function testInjectsNegotiatedVersionAndStashesItOnTheRequest(): void
    {
        $request = new Request();
        $context = $this->builder('apple')->createFromRequest($request, true);

        $this->assertSame('apple', $context[VersionMutationNormalizer::VERSION_CONTEXT_KEY]);
        $this->assertSame('book:read', $context['groups'][0]);
        $this->assertSame('apple', $request->attributes->get(VersionSerializerContextBuilder::REQUEST_ATTRIBUTE));
    }

    public function testNoPreferenceInjectsHead(): void
    {
        $context = $this->builder(null)->createFromRequest(new Request(), true);
        $this->assertSame('cherry', $context[VersionMutationNormalizer::VERSION_CONTEXT_KEY]);
    }

    public function testWriteContextIsUntouched(): void
    {
        $request = new Request();
        $context = $this->builder('apple')->createFromRequest($request, false);

        $this->assertArrayNotHasKey(VersionMutationNormalizer::VERSION_CONTEXT_KEY, $context);
        $this->assertNull($request->attributes->get(VersionSerializerContextBuilder::REQUEST_ATTRIBUTE));
    }

    public function testUnknownVersionBecomesBadRequest(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->builder('banana-split')->createFromRequest(new Request(), true);
    }
}
