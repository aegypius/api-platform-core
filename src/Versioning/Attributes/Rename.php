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
 * Renames a property from its head key to the older version's key.
 *
 * On a class it is a pure key swap. On a method it is a key swap plus value
 * processing: the annotated method computes the value for the "to" key.
 *
 * @experimental
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class Rename implements VersionMutation
{
    /**
     * @param string $from the head property key
     * @param string $to   the older version's property key
     */
    public function __construct(
        public readonly string $from,
        public readonly string $to,
    ) {
    }
}
