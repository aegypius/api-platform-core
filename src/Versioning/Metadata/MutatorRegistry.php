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

namespace ApiPlatform\Versioning\Metadata;

/**
 * Immutable index of the mutations declared across all mutator classes, keyed
 * by resource and by the version each mutator produces ("for").
 *
 * @experimental
 */
final class MutatorRegistry
{
    /**
     * @param array<class-string, array<string, list<BoundMutation>>> $mutations resource => for-version => mutations
     * @param list<string>                                            $forVersions distinct versions produced by mutators
     */
    public function __construct(
        private readonly array $mutations,
        private readonly array $forVersions,
    ) {
    }

    /**
     * @return list<class-string>
     */
    public function getResources(): array
    {
        return array_keys($this->mutations);
    }

    /**
     * The distinct versions produced by the mutators; the source of the global
     * version line.
     *
     * @return list<string>
     */
    public function getForVersions(): array
    {
        return $this->forVersions;
    }

    /**
     * @param class-string $resource
     *
     * @return list<BoundMutation>
     */
    public function getMutations(string $resource, string $for): array
    {
        return $this->mutations[$resource][$for] ?? [];
    }
}
