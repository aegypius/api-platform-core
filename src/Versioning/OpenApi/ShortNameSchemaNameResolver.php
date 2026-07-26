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
 * Resolves schema names by the resource's short class name: API Platform names
 * component schemas after the short name, optionally suffixed by a format
 * ("Book.jsonld") or a serialization group ("Book-read"). Matching is on a
 * boundary so "Book" never captures "Bookmark".
 *
 * @experimental
 */
final class ShortNameSchemaNameResolver implements SchemaNameResolverInterface
{
    public function resolveSchemaNames(string $resource, array $schemaNames): array
    {
        $short = false !== ($pos = strrpos($resource, '\\')) ? substr($resource, $pos + 1) : $resource;

        return array_values(array_filter(
            $schemaNames,
            static fn (string $name): bool => $name === $short
                || str_starts_with($name, $short.'.')
                || str_starts_with($name, $short.'-'),
        ));
    }
}
