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

use ApiPlatform\Versioning\Metadata\MutatorRegistry;

/**
 * Builds the version line from the versions the mutators produce and the
 * configured head, ordered by the given comparator.
 *
 * @experimental
 */
final class VersionGraphFactory
{
    public function create(MutatorRegistry $registry, string $head, VersionComparatorInterface $comparator): VersionGraph
    {
        return VersionGraph::fromVersions($registry->getForVersions(), $head, $comparator);
    }
}
