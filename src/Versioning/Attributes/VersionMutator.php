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
 * Binds a mutator class to one backward-compatible downgrade step
 * ("from" newer version to "to" older version) for a single resource.
 *
 * Repeatable so a single class may serve several resources or steps.
 *
 * @experimental
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final class VersionMutator
{
    /**
     * @param class-string $resource the resource the step applies to
     * @param string       $from     the newer version (head side of the step)
     * @param string       $to       the older version (target side of the step)
     */
    public function __construct(
        public readonly string $resource,
        public readonly string $from,
        public readonly string $to,
    ) {
    }
}
