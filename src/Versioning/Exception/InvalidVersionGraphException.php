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
 * Thrown at build time when the mutator edges do not form a single linear
 * version line (branch, merge, cycle, gap, or unknown head).
 *
 * @experimental
 */
final class InvalidVersionGraphException extends \LogicException implements ExceptionInterface
{
}
