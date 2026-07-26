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

namespace ApiPlatform\Versioning\Symfony\DependencyInjection;

use ApiPlatform\Versioning\Serializer\VersionMutationNormalizer;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Wires the versioning services from the discovered mutator classes:
 *
 * - feeds the mutator class list to the registry factory;
 * - builds a service locator of mutator instances for the response mutator;
 * - decorates every installed resource item normalizer so responses are
 *   downgraded at serialization time.
 *
 * When no mutator is tagged the component stays inert (empty graph = head only).
 *
 * @experimental
 */
final class VersioningPass implements CompilerPassInterface
{
    public const MUTATOR_TAG = 'api_platform.version_mutator';

    /**
     * @var list<string>
     */
    private const ITEM_NORMALIZER_IDS = [
        'api_platform.serializer.normalizer.item',
        'api_platform.jsonld.normalizer.item',
        'api_platform.hal.normalizer.item',
        'api_platform.jsonapi.normalizer.item',
    ];

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('api_platform.versioning.registry')) {
            return;
        }

        $classes = [];
        $locatorMap = [];
        foreach (array_keys($container->findTaggedServiceIds(self::MUTATOR_TAG)) as $id) {
            $class = $container->getDefinition($id)->getClass() ?? $id;
            $classes[] = $class;
            $locatorMap[$class] = new Reference($id);
        }

        $container->getDefinition('api_platform.versioning.registry')->setArgument(0, $classes);

        $container->getDefinition('api_platform.versioning.response_mutator')
            ->setArgument(0, ServiceLocatorTagPass::register($container, $locatorMap));

        foreach (self::ITEM_NORMALIZER_IDS as $normalizerId) {
            if (!$container->hasDefinition($normalizerId) && !$container->hasAlias($normalizerId)) {
                continue;
            }

            $decoratorId = $normalizerId.'.versioning';
            $container->register($decoratorId, VersionMutationNormalizer::class)
                ->setDecoratedService($normalizerId)
                ->setArguments([
                    new Reference($decoratorId.'.inner'),
                    new Reference('api_platform.versioning.chain_resolver'),
                    new Reference('api_platform.versioning.response_mutator'),
                    new Reference('api_platform.versioning.graph'),
                ]);
        }
    }
}
