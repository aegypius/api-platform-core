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

namespace ApiPlatform\Versioning\Symfony\State;

use ApiPlatform\State\SerializerContextBuilderInterface;
use ApiPlatform\Versioning\Serializer\VersionMutationNormalizer;
use ApiPlatform\Versioning\Symfony\EventListener\NegotiateVersionListener;
use Symfony\Component\HttpFoundation\Request;

/**
 * Decorates the serializer context builder to expose the negotiated version
 * (resolved by {@see NegotiateVersionListener}) to the normalizer.
 *
 * Only the normalization (read) side is versioned: versioning is a
 * backward-compatible response concern, so write contexts pass through
 * untouched.
 *
 * @experimental
 */
final class VersionSerializerContextBuilder implements SerializerContextBuilderInterface
{
    public function __construct(private readonly SerializerContextBuilderInterface $decorated)
    {
    }

    public function createFromRequest(Request $request, bool $normalization, ?array $extractedAttributes = null): array
    {
        $context = $this->decorated->createFromRequest($request, $normalization, $extractedAttributes);

        $version = $request->attributes->get(NegotiateVersionListener::REQUEST_ATTRIBUTE);
        if ($normalization && \is_string($version)) {
            $context[VersionMutationNormalizer::VERSION_CONTEXT_KEY] = $version;
        }

        return $context;
    }
}
