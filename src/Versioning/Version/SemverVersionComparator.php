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
 * Orders versions with PHP's version_compare (the default, matching the usual
 * OpenAPI info.version convention: 1.0.0, 2.3.1, ...).
 *
 * @experimental
 */
final class SemverVersionComparator implements VersionComparatorInterface
{
    public function compare(string $a, string $b): int
    {
        return version_compare($a, $b);
    }
}
