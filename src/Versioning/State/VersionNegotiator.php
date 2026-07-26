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

use ApiPlatform\Versioning\Exception\OutOfRangeVersionException;
use ApiPlatform\Versioning\Version\VersionGraph;

/**
 * Turns a raw requested version into the effective version to serve, applying
 * the fixed out-of-range policy:
 *
 * - no preference (null) serves head;
 * - a requestable version (head or older) is served as-is;
 * - anything else (unknown token, or a version above head) is rejected.
 *
 * Since version identifiers are opaque and every known version lives in the
 * graph, there is no "older than the oldest" case to clamp: such a token is
 * simply unknown.
 *
 * @experimental
 */
final class VersionNegotiator
{
    public function negotiate(?string $requested, VersionGraph $graph): string
    {
        if (null === $requested) {
            return $graph->getHead();
        }

        if ($graph->isRequestable($requested)) {
            return $requested;
        }

        throw OutOfRangeVersionException::notRequestable($requested, $graph->getRequestableVersions());
    }
}
