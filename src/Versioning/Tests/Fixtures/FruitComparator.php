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

namespace ApiPlatform\Versioning\Tests\Fixtures;

use ApiPlatform\Versioning\Version\VersionComparatorInterface;

/**
 * Orders opaque fruit-name versions (apple < banana < cherry < date), proving
 * the comparator is pluggable for non-semver schemes.
 */
final class FruitComparator implements VersionComparatorInterface
{
    private const ORDER = ['apple' => 0, 'banana' => 1, 'cherry' => 2, 'date' => 3];

    public function compare(string $a, string $b): int
    {
        return (self::ORDER[$a] ?? -1) <=> (self::ORDER[$b] ?? -1);
    }
}
