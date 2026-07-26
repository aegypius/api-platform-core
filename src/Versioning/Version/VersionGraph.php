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

/**
 * The global version line, derived from mutator downgrade edges.
 *
 * The order comes solely from the edges ("from" is newer than "to"); version
 * identifiers are opaque tokens and are never compared as strings. The line
 * must be a single linear path. Head is a movable pointer into that line:
 * versions above head are defined-but-inactive and cannot be requested.
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
            throw new InvalidVersionGraphException(\sprintf('Head version "%s" is not part of the version graph.', $head));
        }

        $this->versions = $versions;
        $this->headIndex = $headIndex;
    }

    /**
     * @param iterable<VersionStep> $steps
     */
    public static function fromSteps(iterable $steps, string $head): self
    {
        // Deduplicate edges by (from,to); reject branches (one "from" → two
        // "to") and merges (two "from" → one "to").
        $outgoing = [];
        $incoming = [];
        $nodes = [];
        foreach ($steps as $step) {
            $nodes[$step->from] = true;
            $nodes[$step->to] = true;

            if (isset($outgoing[$step->from]) && $outgoing[$step->from] !== $step->to) {
                throw new InvalidVersionGraphException(\sprintf('Version "%s" is downgraded to both "%s" and "%s"; the version line must be linear.', $step->from, $outgoing[$step->from], $step->to));
            }
            if (isset($incoming[$step->to]) && $incoming[$step->to] !== $step->from) {
                throw new InvalidVersionGraphException(\sprintf('Version "%s" is downgraded to from both "%s" and "%s"; the version line must be linear.', $step->to, $incoming[$step->to], $step->from));
            }

            $outgoing[$step->from] = $step->to;
            $incoming[$step->to] = $step->from;
        }

        if (!$nodes) {
            return new self([$head], $head);
        }

        // The newest node is the unique one that is never a downgrade target.
        $newest = null;
        foreach (array_keys($nodes) as $node) {
            if (!isset($incoming[$node])) {
                if (null !== $newest) {
                    throw new InvalidVersionGraphException(\sprintf('The version line is disconnected: "%s" and "%s" are both newest versions.', $newest, $node));
                }
                $newest = $node;
            }
        }

        if (null === $newest) {
            throw new InvalidVersionGraphException('The version line contains a cycle.');
        }

        // Walk the single path from newest to oldest.
        $ordered = [];
        $seen = [];
        $current = $newest;
        while (null !== $current) {
            if (isset($seen[$current])) {
                throw new InvalidVersionGraphException('The version line contains a cycle.');
            }
            $seen[$current] = true;
            $ordered[] = $current;
            $current = $outgoing[$current] ?? null;
        }

        if (\count($ordered) !== \count($nodes)) {
            throw new InvalidVersionGraphException('The version line is disconnected: some versions are not reachable on a single path.');
        }

        return new self($ordered, $head);
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

    public function has(string $version): bool
    {
        return \in_array($version, $this->versions, true);
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
            throw new \InvalidArgumentException(\sprintf('Version "%s" is not requestable.', $target));
        }

        $steps = [];
        for ($i = $this->headIndex; $i < $targetIndex; ++$i) {
            $steps[] = new VersionStep($this->versions[$i], $this->versions[$i + 1]);
        }

        return $steps;
    }
}
