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

namespace ApiPlatform\Versioning\OpenApi;

use ApiPlatform\Versioning\Exception\OutOfRangeVersionException;
use ApiPlatform\Versioning\Serializer\VersionMutationNormalizer;
use ApiPlatform\Versioning\Version\VersionGraph;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Decorates the OpenAPI document normalizer to serve an older version's
 * document — or the Overlay describing it — when a version is negotiated.
 *
 * The negotiated version arrives in the serializer context under the same key
 * the response normalizer uses, so the runtime docs endpoint is versioned for
 * free; the export command sets the same keys from its flags.
 *
 * @experimental
 */
final class VersionedOpenApiNormalizer implements NormalizerInterface
{
    /**
     * Serializer/normalizer context flag: emit the Overlay instead of the doc.
     */
    public const OVERLAY_CONTEXT_KEY = 'api_platform_overlay';

    public function __construct(
        private readonly NormalizerInterface $decorated,
        private readonly VersionGraph $graph,
        private readonly DocumentMutator $documentMutator,
        private readonly OverlayFactory $overlayFactory,
    ) {
    }

    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $document = $this->decorated->normalize($data, $format, $context);

        $version = $context[VersionMutationNormalizer::VERSION_CONTEXT_KEY] ?? null;
        if (null === $version || !\is_array($document)) {
            return $document;
        }
        $version = (string) $version;

        // A version was explicitly requested (e.g. the export command's
        // --api-version): reject an unknown or above-head one, since — unlike
        // the runtime docs endpoint — no listener has pre-validated it.
        if (!$this->graph->isRequestable($version)) {
            throw OutOfRangeVersionException::notRequestable($version, $this->graph->getRequestableVersions());
        }

        /* @var array<string, mixed> $document */
        if ($context[self::OVERLAY_CONTEXT_KEY] ?? false) {
            return $this->overlay($document, $version);
        }

        return $this->documentMutator->mutate($document, $version);
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $this->decorated->supportsNormalization($data, $format, $context);
    }

    /**
     * @return array<class-string|'*'|'object'|string, bool|null>
     */
    public function getSupportedTypes(?string $format): array
    {
        return $this->decorated->getSupportedTypes($format);
    }

    /**
     * @param array<string, mixed> $head
     *
     * @return array<string, mixed>
     */
    private function overlay(array $head, string $version): array
    {
        $mutated = $this->documentMutator->mutate($head, $version);

        $headSchemas = $head['components']['schemas'] ?? [];
        $mutatedSchemas = $mutated['components']['schemas'] ?? [];

        $actions = [];
        foreach ($headSchemas as $name => $schema) {
            if (\is_array($schema) && \is_array($mutatedSchemas[$name] ?? null)) {
                $actions = [...$actions, ...$this->overlayFactory->actionsForSchema((string) $name, $schema, $mutatedSchemas[$name])];
            }
        }

        return $this->overlayFactory->createDocument($head['info']['title'] ?? 'API', $version, $actions);
    }
}
