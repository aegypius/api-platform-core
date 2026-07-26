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
 * Extracts the version a client is asking for from the request.
 *
 * Implementations decide the mechanism (header, query parameter, media-type
 * parameter, URL segment, ...). Returning null means "no preference": the head
 * version is served.
 *
 * @experimental
 */
interface VersionResolverInterface
{
    public function resolve(Request $request): ?string;

    /**
     * The request header this resolver reads, to be added to the response
     * "Vary" header so shared caches key on it. Null when the strategy does not
     * depend on a header.
     */
    public function getVary(): ?string;
}
