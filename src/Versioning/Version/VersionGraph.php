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

use ApiPlatform\Versioning\Exception\InvalidVersionGraphException;
use ApiPlatform\Versioning\Exception\OutOfRangeVersionException;

/**
 * The global version line, derived by ordering the versions the mutators
 * produce (their "for" values) together with head, using a comparator.
 *
 * Head is a movable pointer into that line: versions above head are
 * defined-but-inactive and cannot be requested. Because the line is a sorted
 * set, it is always linear — there is no branch/merge/gap to reject.
 *
 * @experimental
 */
final class VersionGraph
{
    /**
     * @var list<string> newest to oldest
     */
    private readonly array $versions;

    private readonly int $headIndex;

    /**
     * @param list<string> $versions newest to oldest
     */
    private function __construct(array $versions, string $head)
    {
        $headIndex = array_search($head, $versions, true);
        if (false === $headIndex) {
            throw new InvalidVersionGraphException(\sprintf('Head version "%s" is not part of the version line.', $head));
        }

        $this->versions = $versions;
        $this->headIndex = $headIndex;
    }

    /**
     * @param iterable<string> $versions the versions produced by the mutators
     */
    public static function fromVersions(iterable $versions, string $head, VersionComparatorInterface $comparator): self
    {
        $all = [$head];
        foreach ($versions as $version) {
            $all[] = $version;
        }
        $all = array_values(array_unique($all));

        // Ascending (oldest to newest), then reversed to newest first.
        usort($all, static fn (string $a, string $b): int => $comparator->compare($a, $b));

        return new self(array_reverse($all), $head);
    }

    /**
     * @return list<string> every version, newest to oldest (head included)
     */
    public function getVersions(): array
    {
        return $this->versions;
    }

    public function getHead(): string
    {
        return $this->versions[$this->headIndex];
    }

    /**
     * @return list<string> head down to oldest
     */
    public function getRequestableVersions(): array
    {
        return \array_slice($this->versions, $this->headIndex);
    }

    public function isRequestable(string $version): bool
    {
        $index = array_search($version, $this->versions, true);

        return false !== $index && $index >= $this->headIndex;
    }

    /**
     * The downgrade steps from head to the target version, newest first.
     *
     * @return list<VersionStep>
     */
    public function getStepsTo(string $target): array
    {
        $targetIndex = array_search($target, $this->versions, true);
        if (false === $targetIndex || $targetIndex < $this->headIndex) {
            throw OutOfRangeVersionException::notRequestable($target, $this->getRequestableVersions());
        }

        $steps = [];
        for ($i = $this->headIndex; $i < $targetIndex; ++$i) {
            $steps[] = new VersionStep($this->versions[$i], $this->versions[$i + 1]);
        }

        return $steps;
    }
}
