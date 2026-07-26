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
 * Declares that a property's type differs between head and the older version.
 *
 * On a class it is a documentation-only delta (the value is already valid for
 * both types). On a method the annotated method converts the value; either way
 * the "from"/"to" types feed the documentation delta.
 *
 * @experimental
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class ChangeType implements VersionMutationInterface
{
    /**
     * @param string $property the property whose type changes
     * @param string $from     the head JSON type
     * @param string $to       the older version's JSON type
     */
    public function __construct(
        public readonly string $property,
        public readonly string $from,
        public readonly string $to,
    ) {
    }
}
