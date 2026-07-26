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

use ApiPlatform\Versioning\Exception\InvalidVersionException;

/**
 * Orders versions as calendar dates (Stripe-style, e.g. "2024-11-01").
 *
 * @experimental
 */
final class DateVersionComparator implements VersionComparatorInterface
{
    public function compare(string $a, string $b): int
    {
        return $this->parse($a) <=> $this->parse($b);
    }

    private function parse(string $version): \DateTimeImmutable
    {
        try {
            return new \DateTimeImmutable($version);
        } catch (\Exception $e) {
            throw new InvalidVersionException(\sprintf('Version "%s" is not a valid date.', $version), 0, $e);
        }
    }
}
