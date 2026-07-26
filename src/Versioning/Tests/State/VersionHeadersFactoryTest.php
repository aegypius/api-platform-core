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

use ApiPlatform\Versioning\State\VersionHeadersFactory;
use ApiPlatform\Versioning\Tests\Fixtures\FruitComparator;
use ApiPlatform\Versioning\Version\VersionGraph;
use PHPUnit\Framework\TestCase;

final class VersionHeadersFactoryTest extends TestCase
{
    private function factory(): VersionHeadersFactory
    {
        // 'date' is above head 'cherry' (inactive).
        $graph = VersionGraph::fromVersions(['date', 'banana', 'apple'], 'cherry', new FruitComparator());

        return new VersionHeadersFactory($graph);
    }

    public function testAdvertisesServedVersionAndRequestableSetNewestFirst(): void
    {
        $headers = $this->factory()->create('banana');

        $this->assertSame('banana', $headers[VersionHeadersFactory::CONTENT_VERSION]);
        // above-head "date" is excluded; ordered newest -> oldest.
        $this->assertSame('cherry, banana, apple', $headers[VersionHeadersFactory::SUPPORTED_VERSIONS]);
    }
}
