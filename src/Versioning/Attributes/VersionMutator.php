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

namespace ApiPlatform\Versioning\Attributes;

/**
 * Binds a mutator class to the version it produces for a single resource.
 *
 * "for" is the older version this mutator yields (the target side of a downgrade
 * step); the newer side is derived as the next version above it in the ordered
 * version line. A request for that version, or any older one, runs this mutator.
 *
 * Repeatable so a single class may serve several resources or versions.
 *
 * @experimental
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final class VersionMutator
{
    /**
     * @param class-string $resource the resource the mutator applies to
     * @param string       $for      the version this mutator produces (older side)
     */
    public function __construct(
        public readonly string $resource,
        public readonly string $for,
    ) {
    }
}
