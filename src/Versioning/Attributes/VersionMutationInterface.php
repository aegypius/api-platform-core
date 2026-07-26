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

namespace ApiPlatform\Versioning\Attributes;

/**
 * Marker implemented by every mutation attribute (Remove, Rename, ChangeType,
 * Restore) so the metadata factory can collect them by reflection.
 *
 * @experimental
 */
interface VersionMutationInterface
{
}
