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

use ApiPlatform\Versioning\Attributes\ChangeType;
use ApiPlatform\Versioning\Attributes\Remove;
use ApiPlatform\Versioning\Attributes\Rename;
use ApiPlatform\Versioning\Attributes\Restore;
use ApiPlatform\Versioning\Attributes\VersionMutationInterface;
use ApiPlatform\Versioning\Metadata\BoundMutation;

/**
 * Applies a downgrade chain to a single OpenAPI component schema, producing the
 * older version's schema. This is the documentation side of a mutation and is
 * purely declarative: it uses the attribute parameters only and never invokes a
 * mutator method (value logic belongs to the response side).
 *
 * @experimental
 */
final class SchemaMutator
{
    /**
     * @param array<string, mixed> $schema a JSON Schema object (properties, required, ...)
     * @param list<BoundMutation>  $chain
     *
     * @return array<string, mixed>
     */
    public function mutate(array $schema, array $chain): array
    {
        foreach ($chain as $bound) {
            $schema = $this->applyOne($schema, $bound->mutation);
        }

        return $schema;
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    private function applyOne(array $schema, VersionMutationInterface $mutation): array
    {
        if (\is_array($schema['allOf'] ?? null)) {
            return $this->applyToAllOf($schema, $mutation);
        }

        return $this->applyToObject($schema, $mutation);
    }

    /**
     * Hydra/JSON-LD schemas compose the resource's own properties inside
     * allOf (alongside a $ref to a shared base schema), so the mutation must
     * be located and applied there instead of at the schema's top level.
     *
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    private function applyToAllOf(array $schema, VersionMutationInterface $mutation): array
    {
        $allOf = $schema['allOf'];
        $index = null;
        foreach ($allOf as $i => $entry) {
            if (\is_array($entry) && \is_array($entry['properties'] ?? null)) {
                $index = $i;
                break;
            }
        }

        if (null !== $index) {
            $allOf[$index] = $this->applyToObject($allOf[$index], $mutation);
        } else {
            // None of the allOf entries carry properties (e.g. a $ref-only
            // base schema): only add one if the mutation actually produces
            // properties (Restore), to avoid injecting an empty object.
            $mutated = $this->applyToObject(['type' => 'object'], $mutation);
            if (isset($mutated['properties'])) {
                $allOf[] = $mutated;
            }
        }

        $schema['allOf'] = array_values($allOf);

        return $schema;
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    private function applyToObject(array $schema, VersionMutationInterface $mutation): array
    {
        $properties = \is_array($schema['properties'] ?? null) ? $schema['properties'] : [];
        $required = \is_array($schema['required'] ?? null) ? array_values($schema['required']) : [];

        if ($mutation instanceof Remove) {
            unset($properties[$mutation->property]);
            $required = $this->without($required, $mutation->property);
        } elseif ($mutation instanceof Rename) {
            if (\array_key_exists($mutation->from, $properties)) {
                $properties[$mutation->to] = $properties[$mutation->from];
                unset($properties[$mutation->from]);
            }
            $required = $this->replace($required, $mutation->from, $mutation->to);
        } elseif ($mutation instanceof ChangeType) {
            if (\array_key_exists($mutation->property, $properties)) {
                $property = \is_array($properties[$mutation->property]) ? $properties[$mutation->property] : [];
                // The older version's schema shows the older type ("to").
                $property['type'] = $mutation->to;
                $properties[$mutation->property] = $property;
            }
        } elseif ($mutation instanceof Restore) {
            $properties[$mutation->property] = ['type' => $this->inferType($mutation->value)];
        }

        if ($properties) {
            $schema['properties'] = $properties;
        } else {
            unset($schema['properties']);
        }

        if ($required) {
            $schema['required'] = array_values($required);
        } else {
            unset($schema['required']);
        }

        return $schema;
    }

    /**
     * @param list<string> $required
     *
     * @return list<string>
     */
    private function without(array $required, string $property): array
    {
        return array_values(array_filter($required, static fn (string $r): bool => $r !== $property));
    }

    /**
     * @param list<string> $required
     *
     * @return list<string>
     */
    private function replace(array $required, string $from, string $to): array
    {
        return array_map(static fn (string $r): string => $r === $from ? $to : $r, $required);
    }

    private function inferType(mixed $value): string
    {
        return match (true) {
            \is_bool($value) => 'boolean',
            \is_int($value) => 'integer',
            \is_float($value) => 'number',
            \is_array($value) => 'array',
            default => 'string',
        };
    }
}
