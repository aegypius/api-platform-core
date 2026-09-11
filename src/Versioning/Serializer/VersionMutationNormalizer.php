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

namespace ApiPlatform\Versioning\Serializer;

use ApiPlatform\Versioning\State\DowngradeChainResolver;
use ApiPlatform\Versioning\State\ResponseMutator;
use ApiPlatform\Versioning\Version\VersionGraph;
use Symfony\Component\Serializer\Exception\BadMethodCallException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Decorates the resource item normalizer to downgrade a single item to the
 * requested version.
 *
 * It runs per item: the serializer already invokes the item normalizer once per
 * collection member, so collection envelopes are left untouched. When no target
 * version is in the context (or it resolves to head), the decorated output is
 * returned unchanged.
 *
 * @experimental
 */
final class VersionMutationNormalizer implements NormalizerInterface, DenormalizerInterface, SerializerAwareInterface
{
    /**
     * Serializer context key holding the negotiated target version.
     */
    public const VERSION_CONTEXT_KEY = 'api_platform_version';

    public function __construct(
        private readonly NormalizerInterface $decorated,
        private readonly DowngradeChainResolver $chainResolver,
        private readonly ResponseMutator $responseMutator,
        private readonly VersionGraph $graph,
    ) {
    }

    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $normalized = $this->decorated->normalize($data, $format, $context);

        $target = $context[self::VERSION_CONTEXT_KEY] ?? null;
        if (null === $target || !\is_object($data) || !\is_array($normalized)) {
            return $normalized;
        }

        $chain = $this->chainResolver->resolve($data::class, $target);
        if (!$chain) {
            return $normalized;
        }

        /* @var array<string, mixed> $normalized */
        return $this->responseMutator->mutate($normalized, $chain, [
            'resource' => $data,
            'operation' => $context['operation'] ?? null,
            'fromVersion' => $this->graph->getHead(),
            'toVersion' => $target,
            'format' => $format,
        ]);
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

    // The decorated item normalizer is also a denormalizer and serializer-aware.
    // Forward those so decoration stays transparent (writes and the serializer
    // wiring are untouched; versioning only reshapes read output).

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        if (!$this->decorated instanceof DenormalizerInterface) {
            throw new BadMethodCallException(\sprintf('The decorated normalizer "%s" is not a denormalizer.', $this->decorated::class));
        }

        return $this->decorated->denormalize($data, $type, $format, $context);
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return $this->decorated instanceof DenormalizerInterface
            && $this->decorated->supportsDenormalization($data, $type, $format, $context);
    }

    public function setSerializer(SerializerInterface $serializer): void
    {
        if ($this->decorated instanceof SerializerAwareInterface) {
            $this->decorated->setSerializer($serializer);
        }
    }
}
