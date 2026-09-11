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

namespace ApiPlatform\Versioning\Exception;

/**
 * Thrown when a requested version is unknown or above head (not requestable).
 *
 * @experimental
 */
final class OutOfRangeVersionException extends \InvalidArgumentException implements ExceptionInterface
{
    /**
     * @param list<string> $requestableVersions
     */
    public static function notRequestable(string $version, array $requestableVersions): self
    {
        return new self(\sprintf(
            'Version "%s" is not available. Requestable versions: %s.',
            $version,
            implode(', ', $requestableVersions),
        ));
    }
}
