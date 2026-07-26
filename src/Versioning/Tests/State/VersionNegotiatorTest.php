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

namespace ApiPlatform\Versioning\Tests\State;

use ApiPlatform\Versioning\Exception\OutOfRangeVersionException;
use ApiPlatform\Versioning\State\VersionNegotiator;
use ApiPlatform\Versioning\Version\VersionGraph;
use ApiPlatform\Versioning\Version\VersionStep;
use PHPUnit\Framework\TestCase;

final class VersionNegotiatorTest extends TestCase
{
    private function graph(): VersionGraph
    {
        // date is above head cherry (inactive).
        return VersionGraph::fromSteps([
            new VersionStep('date', 'cherry'),
            new VersionStep('cherry', 'banana'),
            new VersionStep('banana', 'apple'),
        ], 'cherry');
    }

    public function testNoPreferenceServesHead(): void
    {
        $this->assertSame('cherry', (new VersionNegotiator())->negotiate(null, $this->graph()));
    }

    public function testRequestableVersionIsServedAsIs(): void
    {
        $this->assertSame('apple', (new VersionNegotiator())->negotiate('apple', $this->graph()));
        $this->assertSame('cherry', (new VersionNegotiator())->negotiate('cherry', $this->graph()));
    }

    public function testAboveHeadIsRejected(): void
    {
        $this->expectException(OutOfRangeVersionException::class);
        (new VersionNegotiator())->negotiate('date', $this->graph());
    }

    public function testUnknownVersionIsRejectedAndListsRequestableVersions(): void
    {
        try {
            (new VersionNegotiator())->negotiate('banana-split', $this->graph());
            $this->fail('expected OutOfRangeVersionException');
        } catch (OutOfRangeVersionException $e) {
            $this->assertStringContainsString('cherry, banana, apple', $e->getMessage());
        }
    }
}
