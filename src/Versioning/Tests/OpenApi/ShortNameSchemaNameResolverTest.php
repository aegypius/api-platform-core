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

namespace ApiPlatform\Versioning\Tests\OpenApi;

use ApiPlatform\Versioning\OpenApi\ShortNameSchemaNameResolver;
use PHPUnit\Framework\TestCase;

final class ShortNameSchemaNameResolverTest extends TestCase
{
    public function testMatchesShortNameFormatAndGroupVariantsOnly(): void
    {
        $names = ['Book', 'Book.jsonld', 'Book-read', 'Bookmark', 'Bookmark.jsonld', 'Author'];

        $resolved = (new ShortNameSchemaNameResolver())->resolveSchemaNames('App\Entity\Book', $names);

        // "Bookmark" must not be captured by the "Book" boundary.
        $this->assertSame(['Book', 'Book.jsonld', 'Book-read'], $resolved);
    }

    public function testHandlesUnqualifiedClassName(): void
    {
        $resolved = (new ShortNameSchemaNameResolver())->resolveSchemaNames('Author', ['Author', 'Book']);
        $this->assertSame(['Author'], $resolved);
    }
}
