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

namespace ApiPlatform\Tests\Fixtures\TestBundle\ApiResource\Versioning;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Tests\Fixtures\TestBundle\Dto\VersionedBook;

#[Get(uriTemplate: '/versioned_book', provider: [VersionedBookOperation::class, 'provide'], output: VersionedBook::class, openapi: false)]
class VersionedBookOperation
{
    public static function provide(): VersionedBook
    {
        return new VersionedBook();
    }
}
