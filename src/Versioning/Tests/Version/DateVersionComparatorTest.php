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

use ApiPlatform\Versioning\Exception\InvalidVersionException;
use ApiPlatform\Versioning\Version\DateVersionComparator;
use PHPUnit\Framework\TestCase;

final class DateVersionComparatorTest extends TestCase
{
    public function testOrdersByDate(): void
    {
        $c = new DateVersionComparator();

        $this->assertLessThan(0, $c->compare('2024-01-01', '2024-11-01'));
        $this->assertGreaterThan(0, $c->compare('2024-11-01', '2024-01-01'));
        $this->assertSame(0, $c->compare('2024-01-01', '2024-01-01'));
    }

    public function testInvalidDateThrows(): void
    {
        $this->expectException(InvalidVersionException::class);
        (new DateVersionComparator())->compare('not-a-date', '2024-01-01');
    }
}
