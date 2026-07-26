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

namespace ApiPlatform\Versioning\Tests\State;

use ApiPlatform\Versioning\State\HeaderVersionResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class HeaderVersionResolverTest extends TestCase
{
    public function testReadsTheDefaultAcceptVersionHeader(): void
    {
        $resolver = new HeaderVersionResolver();
        $request = new Request();
        $request->headers->set('Accept-Version', 'banana');

        $this->assertSame('banana', $resolver->resolve($request));
        $this->assertSame('Accept-Version', $resolver->getVary());
    }

    public function testMissingHeaderResolvesToNull(): void
    {
        $this->assertNull((new HeaderVersionResolver())->resolve(new Request()));
    }

    public function testEmptyHeaderResolvesToNull(): void
    {
        $request = new Request();
        $request->headers->set('Accept-Version', '');

        $this->assertNull((new HeaderVersionResolver())->resolve($request));
    }

    public function testHeaderNameIsConfigurable(): void
    {
        $resolver = new HeaderVersionResolver('X-Api-Version');
        $request = new Request();
        $request->headers->set('X-Api-Version', 'cherry');

        $this->assertSame('cherry', $resolver->resolve($request));
        $this->assertSame('X-Api-Version', $resolver->getVary());
    }
}
