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
 * Drops a property that exists at head but not in the older version.
 *
 * Class-only: there is no value to compute.
 *
 * @experimental
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final class Remove implements VersionMutationInterface
{
    public function __construct(public readonly string $property)
    {
    }
}
