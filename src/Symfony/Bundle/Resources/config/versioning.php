<?php

/*
 * This file is part of the API Platform project.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use ApiPlatform\Versioning\Metadata\MutatorMetadataFactory;
use ApiPlatform\Versioning\Metadata\MutatorRegistry;
use ApiPlatform\Versioning\OpenApi\ChangelogFactory;
use ApiPlatform\Versioning\OpenApi\DocumentMutator;
use ApiPlatform\Versioning\OpenApi\OverlayFactory;
use ApiPlatform\Versioning\OpenApi\SchemaMutator;
use ApiPlatform\Versioning\OpenApi\SchemaNameResolverInterface;
use ApiPlatform\Versioning\OpenApi\ShortNameSchemaNameResolver;
use ApiPlatform\Versioning\OpenApi\VersionedOpenApiNormalizer;
use ApiPlatform\Versioning\State\DowngradeChainResolver;
use ApiPlatform\Versioning\State\HeaderVersionResolver;
use ApiPlatform\Versioning\State\ResponseMutator;
use ApiPlatform\Versioning\State\VersionHeadersFactory;
use ApiPlatform\Versioning\State\VersionNegotiator;
use ApiPlatform\Versioning\State\VersionResolverInterface;
use ApiPlatform\Versioning\Symfony\EventListener\AddVersionHeadersListener;
use ApiPlatform\Versioning\Symfony\EventListener\NegotiateVersionListener;
use ApiPlatform\Versioning\Symfony\State\VersionSerializerContextBuilder;
use ApiPlatform\Versioning\Version\DateVersionComparator;
use ApiPlatform\Versioning\Version\SemverVersionComparator;
use ApiPlatform\Versioning\Version\VersionComparatorInterface;
use ApiPlatform\Versioning\Version\VersionGraph;
use ApiPlatform\Versioning\Version\VersionGraphFactory;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('api_platform.versioning.metadata_factory', MutatorMetadataFactory::class);

    // The mutator class list (argument 0) is populated by VersioningPass from
    // the tagged mutator services.
    $services->set('api_platform.versioning.registry', MutatorRegistry::class)
        ->factory([service('api_platform.versioning.metadata_factory'), 'create'])
        ->args([[]]);

    // Comparators derive the version order. Default is semver; the alias is
    // repointed from config to date or a custom service.
    $services->set('api_platform.versioning.comparator.semver', SemverVersionComparator::class);
    $services->set('api_platform.versioning.comparator.date', DateVersionComparator::class);
    $services->alias(VersionComparatorInterface::class, 'api_platform.versioning.comparator.semver');

    $services->set('api_platform.versioning.graph_factory', VersionGraphFactory::class);

    $services->set('api_platform.versioning.graph', VersionGraph::class)
        ->factory([service('api_platform.versioning.graph_factory'), 'create'])
        ->args([
            service('api_platform.versioning.registry'),
            '%api_platform.version%',
            service(VersionComparatorInterface::class),
        ]);

    $services->set('api_platform.versioning.chain_resolver', DowngradeChainResolver::class)
        ->args([service('api_platform.versioning.graph'), service('api_platform.versioning.registry')]);

    // The mutator service locator (argument 0) is populated by VersioningPass.
    $services->set('api_platform.versioning.response_mutator', ResponseMutator::class)
        ->args([null]);

    $services->set('api_platform.versioning.schema_mutator', SchemaMutator::class);
    $services->set('api_platform.versioning.overlay_factory', OverlayFactory::class);

    $services->set('api_platform.versioning.schema_name_resolver', ShortNameSchemaNameResolver::class);
    $services->alias(SchemaNameResolverInterface::class, 'api_platform.versioning.schema_name_resolver');

    $services->set('api_platform.versioning.changelog_factory', ChangelogFactory::class)
        ->args([
            service('api_platform.versioning.graph'),
            service('api_platform.versioning.registry'),
        ]);

    $services->set('api_platform.versioning.document_mutator', DocumentMutator::class)
        ->args([
            service('api_platform.versioning.graph'),
            service('api_platform.versioning.registry'),
            service('api_platform.versioning.chain_resolver'),
            service('api_platform.versioning.schema_mutator'),
            service(SchemaNameResolverInterface::class),
            service('api_platform.versioning.changelog_factory'),
        ]);

    $services->set('api_platform.versioning.headers_factory', VersionHeadersFactory::class)
        ->args([service('api_platform.versioning.graph')]);

    $services->set('api_platform.versioning.resolver', HeaderVersionResolver::class)
        ->args(['%api_platform.versioning.header%']);
    $services->alias(VersionResolverInterface::class, 'api_platform.versioning.resolver');

    $services->set('api_platform.versioning.negotiator', VersionNegotiator::class);

    $services->set('api_platform.versioning.serializer.context_builder', VersionSerializerContextBuilder::class)
        ->decorate('api_platform.serializer.context_builder')
        ->args([
            service('api_platform.versioning.serializer.context_builder.inner'),
        ]);

    $services->set('api_platform.versioning.openapi.normalizer', VersionedOpenApiNormalizer::class)
        ->decorate('api_platform.openapi.normalizer')
        ->args([
            service('api_platform.versioning.openapi.normalizer.inner'),
            service('api_platform.versioning.graph'),
            service('api_platform.versioning.document_mutator'),
            service('api_platform.versioning.overlay_factory'),
        ]);

    $services->set('api_platform.versioning.event_listener.negotiate', NegotiateVersionListener::class)
        ->args([
            service('api_platform.versioning.resolver'),
            service('api_platform.versioning.negotiator'),
            service('api_platform.versioning.graph'),
        ])
        ->tag('kernel.event_listener', ['event' => 'kernel.request', 'method' => 'onKernelRequest']);

    $services->set('api_platform.versioning.event_listener.add_headers', AddVersionHeadersListener::class)
        ->args([
            service('api_platform.versioning.headers_factory'),
            service('api_platform.versioning.resolver'),
        ])
        ->tag('kernel.event_listener', ['event' => 'kernel.response', 'method' => 'onKernelResponse']);
};
