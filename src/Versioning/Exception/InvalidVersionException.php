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
 * Thrown when a version identifier cannot be interpreted by the active
 * comparator (for example a non-date value under the date comparator).
 *
 * @experimental
 */
final class InvalidVersionException extends \InvalidArgumentException implements ExceptionInterface
{
}
