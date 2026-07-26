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

namespace ApiPlatform\Versioning\Tests\OpenApi;

use ApiPlatform\Versioning\Metadata\MutatorMetadataFactory;
use ApiPlatform\Versioning\OpenApi\ChangelogFactory;
use ApiPlatform\Versioning\Tests\Fixtures\BookBananaToApple;
use ApiPlatform\Versioning\Tests\Fixtures\BookCherryToBanana;
use ApiPlatform\Versioning\Tests\Fixtures\DropInternalNotes;
use ApiPlatform\Versioning\Version\VersionGraph;
use PHPUnit\Framework\TestCase;

final class ChangelogFactoryTest extends TestCase
{
    public function testBuildsPerStepChangeDescriptions(): void
    {
        $registry = (new MutatorMetadataFactory())->create([
            BookCherryToBanana::class,
            BookBananaToApple::class,
            DropInternalNotes::class,
        ]);
        $graph = VersionGraph::fromSteps($registry->getSteps(), 'cherry');

        $changelog = (new ChangelogFactory($graph, $registry))->create();

        $this->assertCount(2, $changelog);

        $this->assertSame('cherry', $changelog[0]['from']);
        $this->assertSame('banana', $changelog[0]['to']);
        $this->assertContains('Book: removed "discount"', $changelog[0]['changes']);
        $this->assertContains('Book: renamed "title" to "name"', $changelog[0]['changes']);
        $this->assertContains('Review: removed "internalNotes"', $changelog[0]['changes']);

        $this->assertSame('banana', $changelog[1]['from']);
        $this->assertSame('apple', $changelog[1]['to']);
        $this->assertContains('Book: renamed "lastUpdated" to "updatedAt"', $changelog[1]['changes']);
        $this->assertContains('Book: changed "available" type from boolean to integer', $changelog[1]['changes']);
    }
}
