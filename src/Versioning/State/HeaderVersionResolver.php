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

use Symfony\Component\HttpFoundation\Request;

/**
 * The default resolver: reads the requested version from a request header
 * (Accept-Version by default) and advertises it as the cache Vary key.
 *
 * @experimental
 */
final class HeaderVersionResolver implements VersionResolverInterface
{
    public function __construct(private readonly string $headerName = 'Accept-Version')
    {
    }

    public function resolve(Request $request): ?string
    {
        $version = $request->headers->get($this->headerName);

        return null === $version || '' === $version ? null : $version;
    }

    public function getVary(): string
    {
        return $this->headerName;
    }
}
