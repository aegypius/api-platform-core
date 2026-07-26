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
 * Re-adds a property that existed in the older version but was dropped at head.
 *
 * On a class a static $value is supplied. On a method the annotated method
 * computes the value.
 *
 * @experimental
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class Restore implements VersionMutationInterface
{
    /**
     * @param string $property the property to re-add
     * @param mixed  $value    a static value used when declared on a class
     */
    public function __construct(
        public readonly string $property,
        public readonly mixed $value = null,
    ) {
    }
}
