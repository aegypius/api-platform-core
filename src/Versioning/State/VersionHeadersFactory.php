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

use ApiPlatform\Versioning\Version\VersionGraph;

/**
 * Builds the discoverability response headers for a served version: the version
 * actually served and the ordered set of requestable versions (newest first).
 *
 * The cache "Vary" header is the resolver's concern (it knows which request
 * header it reads) and is applied by the framework glue.
 *
 * @experimental
 */
final class VersionHeadersFactory
{
    public const CONTENT_VERSION = 'Content-Version';
    public const SUPPORTED_VERSIONS = 'API-Supported-Versions';

    public function __construct(private readonly VersionGraph $graph)
    {
    }

    /**
     * @return array<string, string>
     */
    public function create(string $servedVersion): array
    {
        return [
            self::CONTENT_VERSION => $servedVersion,
            // getRequestableVersions() is ordered head-to-oldest, i.e. newest first.
            self::SUPPORTED_VERSIONS => implode(', ', $this->graph->getRequestableVersions()),
        ];
    }
}
