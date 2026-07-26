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

use ApiPlatform\Versioning\Version\VersionStep;

/**
 * Immutable index of the mutations declared across all mutator classes, keyed
 * by resource and downgrade step.
 *
 * @experimental
 */
final class MutatorRegistry
{
    /**
     * @param array<class-string, array<string, list<BoundMutation>>> $mutations resource => "from>to" => mutations
     * @param list<VersionStep>                                       $steps     distinct downgrade edges
     */
    public function __construct(
        private readonly array $mutations,
        private readonly array $steps,
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
     * Distinct downgrade edges across every resource; the source of the global
     * version line.
     *
     * @return list<VersionStep>
     */
    public function getSteps(): array
    {
        return $this->steps;
    }

    /**
     * @param class-string $resource
     *
     * @return list<BoundMutation>
     */
    public function getMutations(string $resource, string $from, string $to): array
    {
        return $this->mutations[$resource][self::key($from, $to)] ?? [];
    }

    public static function key(string $from, string $to): string
    {
        return $from.'>'.$to;
    }
}
