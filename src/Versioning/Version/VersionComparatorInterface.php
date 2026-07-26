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

namespace ApiPlatform\Versioning\Version;

/**
 * Total order over version identifiers, used to derive the version line from the
 * mutators' "for" values. Implement this for a custom versioning scheme.
 *
 * @experimental
 */
interface VersionComparatorInterface
{
    /**
     * Returns < 0 if $a is older than $b, 0 if equal, > 0 if $a is newer.
     */
    public function compare(string $a, string $b): int;
}
