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

/**
 * A single downgrade edge: "from" (newer) to "to" (older).
 *
 * @experimental
 */
final class VersionStep
{
    public function __construct(
        public readonly string $from,
        public readonly string $to,
    ) {
    }
}
