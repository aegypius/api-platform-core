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

namespace ApiPlatform\Versioning\OpenApi;

use ApiPlatform\Versioning\Attributes\ChangeType;
use ApiPlatform\Versioning\Attributes\Remove;
use ApiPlatform\Versioning\Attributes\Rename;
use ApiPlatform\Versioning\Attributes\Restore;
use ApiPlatform\Versioning\Attributes\VersionMutation;
use ApiPlatform\Versioning\Metadata\MutatorRegistry;
use ApiPlatform\Versioning\Util\ShortName;
use ApiPlatform\Versioning\Version\VersionGraph;

/**
 * Builds a human-readable changelog from the mutation metadata: one entry per
 * downgrade step describing how each resource differs between the two versions.
 * Suitable for the x-api-changelog documentation extension.
 *
 * @experimental
 */
final class ChangelogFactory
{
    public function __construct(
        private readonly VersionGraph $graph,
        private readonly MutatorRegistry $registry,
    ) {
    }

    /**
     * @return list<array{from: string, to: string, changes: list<string>}>
     */
    public function create(): array
    {
        $entries = [];
        $versions = $this->graph->getRequestableVersions(); // newest first

        for ($i = 0, $n = \count($versions) - 1; $i < $n; ++$i) {
            $from = $versions[$i];
            $to = $versions[$i + 1];

            $changes = [];
            foreach ($this->registry->getResources() as $resource) {
                // The step from $from down to $to is described by the mutations
                // authored "for" $to (the older side).
                foreach ($this->registry->getMutations($resource, $to) as $bound) {
                    $changes[] = $this->describe($resource, $bound->mutation);
                }
            }

            if ($changes) {
                $entries[] = ['from' => $from, 'to' => $to, 'changes' => $changes];
            }
        }

        return $entries;
    }

    private function describe(string $resource, VersionMutation $mutation): string
    {
        $short = ShortName::of($resource);

        $detail = match (true) {
            $mutation instanceof Remove => \sprintf('removed "%s"', $mutation->property),
            $mutation instanceof Rename => \sprintf('renamed "%s" to "%s"', $mutation->from, $mutation->to),
            $mutation instanceof ChangeType => \sprintf('changed "%s" type from %s to %s', $mutation->property, $mutation->from, $mutation->to),
            $mutation instanceof Restore => \sprintf('restored "%s"', $mutation->property),
            default => 'changed',
        };

        return \sprintf('%s: %s', $short, $detail);
    }
}
