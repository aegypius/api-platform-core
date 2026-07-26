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

namespace ApiPlatform\Versioning\Metadata;

use ApiPlatform\Versioning\Attributes\VersionMutationInterface;

/**
 * A single mutation resolved from a mutator class, with the mutator class it
 * came from and, for method-level mutations, the method that computes the value.
 *
 * @experimental
 */
final class BoundMutation
{
    /**
     * @param class-string $mutatorClass
     */
    public function __construct(
        public readonly VersionMutationInterface $mutation,
        public readonly string $mutatorClass,
        public readonly ?string $method = null,
    ) {
    }
}
