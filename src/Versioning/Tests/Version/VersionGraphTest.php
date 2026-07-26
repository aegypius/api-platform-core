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

use ApiPlatform\Versioning\Exception\OutOfRangeVersionException;
use ApiPlatform\Versioning\Tests\Fixtures\FruitComparator;
use ApiPlatform\Versioning\Version\SemverVersionComparator;
use ApiPlatform\Versioning\Version\VersionGraph;
use ApiPlatform\Versioning\Version\VersionStep;
use PHPUnit\Framework\TestCase;

final class VersionGraphTest extends TestCase
{
    public function testOrderIsDerivedByTheComparatorNewestToOldest(): void
    {
        // opaque tokens, ordered only by the pluggable comparator.
        $graph = VersionGraph::fromVersions(['apple', 'banana'], 'cherry', new FruitComparator());

        $this->assertSame(['cherry', 'banana', 'apple'], $graph->getVersions());
        $this->assertSame('cherry', $graph->getHead());
    }

    public function testDefaultSemverOrdering(): void
    {
        $graph = VersionGraph::fromVersions(['1.0.0', '2.0.0'], '3.0.0', new SemverVersionComparator());

        $this->assertSame(['3.0.0', '2.0.0', '1.0.0'], $graph->getVersions());
    }

    public function testHeadIsAMovablePointerAndAboveHeadIsNotRequestable(): void
    {
        // 'date' is above head 'cherry' (defined-but-inactive).
        $graph = VersionGraph::fromVersions(['date', 'banana', 'apple'], 'cherry', new FruitComparator());

        $this->assertSame(['date', 'cherry', 'banana', 'apple'], $graph->getVersions());
        $this->assertSame(['cherry', 'banana', 'apple'], $graph->getRequestableVersions());
        $this->assertTrue($graph->isRequestable('banana'));
        $this->assertFalse($graph->isRequestable('date'));
        $this->assertFalse($graph->isRequestable('unknown'));
    }

    public function testStepsToReturnsHeadToTargetNewestFirst(): void
    {
        $graph = VersionGraph::fromVersions(['banana', 'apple'], 'cherry', new FruitComparator());

        $steps = array_map(
            static fn (VersionStep $s): array => [$s->from, $s->to],
            $graph->getStepsTo('apple'),
        );
        $this->assertSame([['cherry', 'banana'], ['banana', 'apple']], $steps);
    }

    public function testStepsToHeadIsEmpty(): void
    {
        $graph = VersionGraph::fromVersions(['banana'], 'cherry', new FruitComparator());
        $this->assertSame([], $graph->getStepsTo('cherry'));
    }

    public function testStepsToUnknownOrAboveHeadThrows(): void
    {
        $graph = VersionGraph::fromVersions(['date', 'banana'], 'cherry', new FruitComparator());
        $this->expectException(OutOfRangeVersionException::class);
        $graph->getStepsTo('date'); // above head, inactive
    }

    public function testEmptyVersionsHasOnlyHead(): void
    {
        $graph = VersionGraph::fromVersions([], 'apple', new FruitComparator());
        $this->assertSame(['apple'], $graph->getVersions());
        $this->assertSame(['apple'], $graph->getRequestableVersions());
    }

    public function testHeadPresentAmongForValuesIsDeduplicated(): void
    {
        $graph = VersionGraph::fromVersions(['cherry', 'banana'], 'cherry', new FruitComparator());
        $this->assertSame(['cherry', 'banana'], $graph->getVersions());
    }
}
