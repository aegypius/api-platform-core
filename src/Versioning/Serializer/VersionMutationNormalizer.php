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
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

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
final class VersionMutationNormalizer implements NormalizerInterface
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

        /** @var array<string, mixed> $normalized */
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
}
