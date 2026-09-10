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
 * Emits a spec-compliant OpenAPI Overlay (1.0.0) document describing a version
 * delta, derived by diffing head component schemas against their mutated form
 * (produced by {@see SchemaMutator}). Emitting from the diff keeps the overlay
 * consistent with the applied documentation and lets renames/restores carry
 * their real schema for free.
 *
 * @see https://spec.openapis.org/overlay/v1.0.0.html
 *
 * @experimental
 */
final class OverlayFactory
{
    public const VERSION = '1.0.0';

    /**
     * Diffs one component schema and returns the Overlay actions for it.
     *
     * @param array<string, mixed> $head    the head schema
     * @param array<string, mixed> $mutated the older version's schema
     *
     * @return list<array<string, mixed>>
     */
    public function actionsForSchema(string $schemaName, array $head, array $mutated): array
    {
        [$headProperties, $headRequired, $headPath] = $this->propertiesOf($head);
        [$mutatedProperties, $mutatedRequired] = $this->propertiesOf($mutated);

        $base = \sprintf("$.components.schemas['%s']%s", $schemaName, $headPath);
        $actions = [];

        // Removed properties.
        foreach ($headProperties as $name => $_) {
            if (!\array_key_exists($name, $mutatedProperties)) {
                $actions[] = ['target' => \sprintf("%s.properties['%s']", $base, $name), 'remove' => true];
            }
        }

        // Added or changed properties.
        foreach ($mutatedProperties as $name => $schema) {
            if (!\array_key_exists($name, $headProperties) || $headProperties[$name] !== $schema) {
                $actions[] = ['target' => \sprintf("%s.properties['%s']", $base, $name), 'update' => $schema];
            }
        }

        // Required set, when it changed (arrays are replaced wholesale by update).
        if ($headRequired !== $mutatedRequired) {
            $actions[] = ['target' => $base.'.required', 'update' => $mutatedRequired];
        }

        return $actions;
    }

    /**
     * Reads a schema's own properties/required, following JSON-LD/Hydra's
     * allOf composition (the resource's properties live in the allOf member
     * that isn't a bare $ref) so overlay diffing sees the same shape
     * {@see SchemaMutator} mutates.
     *
     * @param array<string, mixed> $schema
     *
     * @return array{0: array<string, mixed>, 1: list<string>, 2: string}
     */
    private function propertiesOf(array $schema): array
    {
        $path = '';

        if (\is_array($schema['allOf'] ?? null)) {
            foreach ($schema['allOf'] as $i => $entry) {
                if (\is_array($entry) && \is_array($entry['properties'] ?? null)) {
                    $schema = $entry;
                    $path = ".allOf[{$i}]";
                    break;
                }
            }
        }

        $properties = \is_array($schema['properties'] ?? null) ? $schema['properties'] : [];
        $required = array_values($schema['required'] ?? []);

        return [$properties, $required, $path];
    }

    /**
     * Assembles a full Overlay document from a set of actions.
     *
     * @param list<array<string, mixed>> $actions
     *
     * @return array<string, mixed>
     */
    public function createDocument(string $title, string $version, array $actions): array
    {
        return [
            'overlay' => self::VERSION,
            'info' => ['title' => $title, 'version' => $version],
            'actions' => $actions,
        ];
    }
}
