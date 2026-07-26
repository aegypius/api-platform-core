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

use ApiPlatform\Versioning\Exception\InvalidVersionGraphException;
use ApiPlatform\Versioning\Version\VersionGraph;
use ApiPlatform\Versioning\Version\VersionStep;
use PHPUnit\Framework\TestCase;

final class VersionGraphTest extends TestCase
{
    /**
     * @param list<array{0: string, 1: string}> $edges
     */
    private static function graph(array $edges, string $head): VersionGraph
    {
        return VersionGraph::fromSteps(
            array_map(static fn (array $e): VersionStep => new VersionStep($e[0], $e[1]), $edges),
            $head,
        );
    }

    public function testLinearOrderIsDerivedFromEdgesNewestToOldest(): void
    {
        // opaque tokens: order comes only from the edges, not string compare.
        $graph = self::graph([['cherry', 'banana'], ['banana', 'apple']], 'cherry');

        $this->assertSame(['cherry', 'banana', 'apple'], $graph->getVersions());
        $this->assertSame('cherry', $graph->getHead());
    }

    public function testHeadIsAMovablePointerAndAboveHeadIsNotRequestable(): void
    {
        // 'date' exists above head 'cherry' (defined-but-inactive).
        $graph = self::graph([['date', 'cherry'], ['cherry', 'banana'], ['banana', 'apple']], 'cherry');

        $this->assertSame(['date', 'cherry', 'banana', 'apple'], $graph->getVersions());
        $this->assertSame(['cherry', 'banana', 'apple'], $graph->getRequestableVersions());
        $this->assertTrue($graph->isRequestable('banana'));
        $this->assertFalse($graph->isRequestable('date'));
        $this->assertFalse($graph->isRequestable('unknown'));
    }

    public function testStepsToReturnsHeadToTargetNewestFirst(): void
    {
        $graph = self::graph([['cherry', 'banana'], ['banana', 'apple']], 'cherry');

        $steps = $graph->getStepsTo('apple');
        $this->assertSame(
            [['cherry', 'banana'], ['banana', 'apple']],
            array_map(static fn (VersionStep $s): array => [$s->from, $s->to], $steps),
        );
    }

    public function testStepsToHeadIsEmpty(): void
    {
        $graph = self::graph([['cherry', 'banana']], 'cherry');
        $this->assertSame([], $graph->getStepsTo('cherry'));
    }

    public function testStepsToUnknownOrAboveHeadThrows(): void
    {
        $graph = self::graph([['date', 'cherry'], ['cherry', 'banana']], 'cherry');
        $this->expectException(\InvalidArgumentException::class);
        $graph->getStepsTo('date'); // above head, inactive
    }

    public function testEmptyGraphHasOnlyHead(): void
    {
        $graph = VersionGraph::fromSteps([], 'apple');
        $this->assertSame(['apple'], $graph->getVersions());
        $this->assertSame(['apple'], $graph->getRequestableVersions());
    }

    public function testDuplicateEdgesAreDeduplicated(): void
    {
        // two resources contributing the same global edge.
        $graph = self::graph([['cherry', 'banana'], ['cherry', 'banana']], 'cherry');
        $this->assertSame(['cherry', 'banana'], $graph->getVersions());
    }

    public function testBranchIsRejected(): void
    {
        $this->expectException(InvalidVersionGraphException::class);
        self::graph([['cherry', 'banana'], ['cherry', 'apple']], 'cherry');
    }

    public function testMergeIsRejected(): void
    {
        $this->expectException(InvalidVersionGraphException::class);
        self::graph([['cherry', 'apple'], ['banana', 'apple']], 'cherry');
    }

    public function testCycleIsRejected(): void
    {
        $this->expectException(InvalidVersionGraphException::class);
        self::graph([['a', 'b'], ['b', 'c'], ['c', 'a']], 'a');
    }

    public function testGapIsRejected(): void
    {
        // two disconnected chains.
        $this->expectException(InvalidVersionGraphException::class);
        self::graph([['cherry', 'banana'], ['grape', 'fig']], 'cherry');
    }

    public function testHeadMustExistInGraph(): void
    {
        $this->expectException(InvalidVersionGraphException::class);
        self::graph([['cherry', 'banana']], 'melon');
    }
}
