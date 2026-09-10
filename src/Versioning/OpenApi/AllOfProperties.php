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
 * Locates the allOf entry that carries a Hydra/JSON-LD schema's own
 * properties (the entry alongside a $ref to a shared base schema).
 * {@see SchemaMutator} and {@see OverlayFactory} must agree on which entry
 * that is, so both look it up here.
 *
 * @experimental
 */
final class AllOfProperties
{
    /**
     * @param list<mixed> $allOf
     */
    public static function indexOf(array $allOf): ?int
    {
        foreach ($allOf as $i => $entry) {
            if (\is_array($entry) && \is_array($entry['properties'] ?? null)) {
                return $i;
            }
        }

        return null;
    }
}
