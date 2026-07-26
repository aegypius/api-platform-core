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

namespace ApiPlatform\Versioning\State;

use ApiPlatform\Versioning\Metadata\BoundMutation;
use ApiPlatform\Versioning\Metadata\MutatorRegistry;
use ApiPlatform\Versioning\Version\VersionGraph;

/**
 * Resolves the ordered list of mutations to apply to a resource to bring it
 * from head down to a target version: the mutations of every step on the
 * head-to-target span, concatenated newest-first. Sparse steps contribute
 * nothing.
 *
 * @experimental
 */
final class DowngradeChainResolver
{
    public function __construct(
        private readonly VersionGraph $graph,
        private readonly MutatorRegistry $registry,
    ) {
    }

    /**
     * @param class-string $resource
     *
     * @return list<BoundMutation>
     */
    public function resolve(string $resource, string $target): array
    {
        $chain = [];
        foreach ($this->graph->getStepsTo($target) as $step) {
            // A step's mutations are the ones authored "for" its older side.
            foreach ($this->registry->getMutations($resource, $step->to) as $mutation) {
                $chain[] = $mutation;
            }
        }

        return $chain;
    }
}
