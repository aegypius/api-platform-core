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

namespace ApiPlatform\Versioning\Tests\Version;

use ApiPlatform\Versioning\Version\SemverVersionComparator;
use PHPUnit\Framework\TestCase;

final class SemverVersionComparatorTest extends TestCase
{
    public function testOrders(): void
    {
        $c = new SemverVersionComparator();

        $this->assertLessThan(0, $c->compare('1.0.0', '2.0.0'));
        $this->assertGreaterThan(0, $c->compare('2.0.0', '1.0.0'));
        $this->assertSame(0, $c->compare('1.0.0', '1.0.0'));
        // numeric ordering, not lexical (1.9 < 1.10).
        $this->assertLessThan(0, $c->compare('1.9.0', '1.10.0'));
    }
}
