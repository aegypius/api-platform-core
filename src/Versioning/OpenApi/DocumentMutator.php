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

use ApiPlatform\Versioning\Metadata\MutatorRegistry;
use ApiPlatform\Versioning\State\DowngradeChainResolver;
use ApiPlatform\Versioning\Version\VersionGraph;

/**
 * Produces the OpenAPI document for an older version by mutating, in place, the
 * component schemas each resource owns (paths reference them by $ref, so
 * operations follow automatically). It also sets info.version and advertises
 * the requestable versions under the x-api-versions extension.
 *
 * @experimental
 */
final class DocumentMutator
{
    public function __construct(
        private readonly VersionGraph $graph,
        private readonly MutatorRegistry $registry,
        private readonly DowngradeChainResolver $chainResolver,
        private readonly SchemaMutator $schemaMutator,
        private readonly SchemaNameResolverInterface $schemaNameResolver,
        private readonly ChangelogFactory $changelogFactory,
    ) {
    }

    /**
     * @param array<string, mixed> $document the head OpenAPI document (normalized)
     *
     * @return array<string, mixed>
     */
    public function mutate(array $document, string $target): array
    {
        $schemas = $document['components']['schemas'] ?? [];
        if (\is_array($schemas) && $schemas) {
            $schemaNames = array_keys($schemas);
            foreach ($this->registry->getResources() as $resource) {
                $chain = $this->chainResolver->resolve($resource, $target);
                if (!$chain) {
                    continue;
                }

                foreach ($this->schemaNameResolver->resolveSchemaNames($resource, $schemaNames) as $name) {
                    if (isset($schemas[$name]) && \is_array($schemas[$name])) {
                        $schemas[$name] = $this->schemaMutator->mutate($schemas[$name], $chain);
                    }
                }
            }

            $document['components']['schemas'] = $schemas;
        }

        $document['info']['version'] = $target;
        $document['info']['x-api-versions'] = $this->graph->getRequestableVersions();
        $document['info']['x-api-changelog'] = $this->changelogFactory->create();

        return $document;
    }
}
