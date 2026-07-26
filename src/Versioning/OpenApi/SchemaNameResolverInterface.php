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

/**
 * Maps a resource class to the OpenAPI component-schema names that describe it
 * (a resource may own several: per format and per serialization group).
 *
 * @experimental
 */
interface SchemaNameResolverInterface
{
    /**
     * @param class-string $resource
     * @param list<string> $schemaNames all component-schema names in the document
     *
     * @return list<string>
     */
    public function resolveSchemaNames(string $resource, array $schemaNames): array;
}
