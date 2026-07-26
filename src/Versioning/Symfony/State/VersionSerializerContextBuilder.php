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
use ApiPlatform\Versioning\Exception\OutOfRangeVersionException;
use ApiPlatform\Versioning\Serializer\VersionMutationNormalizer;
use ApiPlatform\Versioning\State\VersionNegotiator;
use ApiPlatform\Versioning\State\VersionResolverInterface;
use ApiPlatform\Versioning\Version\VersionGraph;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Decorates the serializer context builder to negotiate the requested version
 * and expose it to the normalizer.
 *
 * Only the normalization (read) side is versioned: versioning is a
 * backward-compatible response concern, so write contexts pass through
 * untouched. The negotiated version is also stashed on the request so the
 * response listener can advertise it.
 *
 * @experimental
 */
final class VersionSerializerContextBuilder implements SerializerContextBuilderInterface
{
    /**
     * Request attribute holding the negotiated version for the response listener.
     */
    public const REQUEST_ATTRIBUTE = '_api_platform_version';

    public function __construct(
        private readonly SerializerContextBuilderInterface $decorated,
        private readonly VersionResolverInterface $resolver,
        private readonly VersionNegotiator $negotiator,
        private readonly VersionGraph $graph,
    ) {
    }

    public function createFromRequest(Request $request, bool $normalization, ?array $extractedAttributes = null): array
    {
        $context = $this->decorated->createFromRequest($request, $normalization, $extractedAttributes);

        if (!$normalization) {
            return $context;
        }

        try {
            $version = $this->negotiator->negotiate($this->resolver->resolve($request), $this->graph);
        } catch (OutOfRangeVersionException $e) {
            throw new BadRequestHttpException($e->getMessage(), $e);
        }

        $context[VersionMutationNormalizer::VERSION_CONTEXT_KEY] = $version;
        $request->attributes->set(self::REQUEST_ATTRIBUTE, $version);

        return $context;
    }
}
