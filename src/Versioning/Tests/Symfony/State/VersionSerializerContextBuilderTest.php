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
use ApiPlatform\Versioning\Symfony\EventListener\NegotiateVersionListener;
use ApiPlatform\Versioning\Symfony\State\VersionSerializerContextBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class VersionSerializerContextBuilderTest extends TestCase
{
    private function builder(): VersionSerializerContextBuilder
    {
        $inner = new class implements SerializerContextBuilderInterface {
            public function createFromRequest(Request $request, bool $normalization, ?array $extractedAttributes = null): array
            {
                return ['groups' => ['book:read']];
            }
        };

        return new VersionSerializerContextBuilder($inner);
    }

    private function requestWithVersion(string $version): Request
    {
        $request = new Request();
        $request->attributes->set(NegotiateVersionListener::REQUEST_ATTRIBUTE, $version);

        return $request;
    }

    public function testExposesNegotiatedVersionToTheNormalizer(): void
    {
        $context = $this->builder()->createFromRequest($this->requestWithVersion('apple'), true);

        $this->assertSame('apple', $context[VersionMutationNormalizer::VERSION_CONTEXT_KEY]);
        $this->assertSame('book:read', $context['groups'][0]);
    }

    public function testWriteContextIsUntouched(): void
    {
        $context = $this->builder()->createFromRequest($this->requestWithVersion('apple'), false);
        $this->assertArrayNotHasKey(VersionMutationNormalizer::VERSION_CONTEXT_KEY, $context);
    }

    public function testNoNegotiatedVersionLeavesContextUnchanged(): void
    {
        $context = $this->builder()->createFromRequest(new Request(), true);
        $this->assertArrayNotHasKey(VersionMutationNormalizer::VERSION_CONTEXT_KEY, $context);
    }
}
