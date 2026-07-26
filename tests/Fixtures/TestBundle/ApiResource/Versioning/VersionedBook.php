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

/**
 * Head (version "2.0.0") shape of a book, used by the experimental versioning
 * functional test. The resource is its own output so its OpenAPI schema is
 * named after it ("VersionedBook").
 */
#[Get(uriTemplate: '/versioned_book', provider: [VersionedBook::class, 'provide'])]
final class VersionedBook
{
    public function __construct(
        public string $title = 'The Pragmatic Programmer',
        public int $discount = 10,
        public bool $available = true,
        public string $lastUpdated = '2026-01-02T03:04:05+02:00',
        public string $isbn = '978-0135957059',
    ) {
    }

    public static function provide(): self
    {
        return new self();
    }
}
