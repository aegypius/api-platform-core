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

namespace ApiPlatform\Versioning\Tests\Fixtures;

use ApiPlatform\Versioning\OpenApi\SchemaNameResolverInterface;
use ApiPlatform\Versioning\Util\ShortName;

/**
 * Mimics a resource whose schema is a separate output DTO named "<ShortName>Output"
 * instead of the resource's short name, unlike the default {@see \ApiPlatform\Versioning\OpenApi\ShortNameSchemaNameResolver}.
 */
final class CustomSchemaNameResolver implements SchemaNameResolverInterface
{
    public function resolveSchemaNames(string $resource, array $schemaNames): array
    {
        $name = ShortName::of($resource).'Output';

        return array_values(array_filter($schemaNames, static fn (string $schemaName): bool => $schemaName === $name));
    }
}
