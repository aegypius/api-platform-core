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

namespace ApiPlatform\Versioning\Tests\Symfony\EventListener;

use ApiPlatform\Metadata\Exception\BadRequestException;
use ApiPlatform\Versioning\State\VersionNegotiator;
use ApiPlatform\Versioning\State\VersionResolverInterface;
use ApiPlatform\Versioning\Symfony\EventListener\NegotiateVersionListener;
use ApiPlatform\Versioning\Tests\Fixtures\FruitComparator;
use ApiPlatform\Versioning\Version\VersionGraph;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class NegotiateVersionListenerTest extends TestCase
{
    private function listener(?string $resolved): NegotiateVersionListener
    {
        $resolver = new class($resolved) implements VersionResolverInterface {
            public function __construct(private readonly ?string $resolved)
            {
            }

            public function resolve(Request $request): ?string
            {
                return $this->resolved;
            }

            public function getVary(): ?string
            {
                return 'Accept-Version';
            }
        };

        $graph = VersionGraph::fromVersions(['banana', 'apple'], 'cherry', new FruitComparator());

        return new NegotiateVersionListener($resolver, new VersionNegotiator(), $graph);
    }

    private function event(Request $request): RequestEvent
    {
        return new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
    }

    public function testStashesNegotiatedVersionOnTheRequest(): void
    {
        $request = new Request();
        $this->listener('apple')->onKernelRequest($this->event($request));

        $this->assertSame('apple', $request->attributes->get(NegotiateVersionListener::REQUEST_ATTRIBUTE));
    }

    public function testNoPreferenceStashesHead(): void
    {
        $request = new Request();
        $this->listener(null)->onKernelRequest($this->event($request));

        $this->assertSame('cherry', $request->attributes->get(NegotiateVersionListener::REQUEST_ATTRIBUTE));
    }

    public function testUnknownVersionThrowsBadRequest(): void
    {
        $this->expectException(BadRequestException::class);
        $this->listener('banana-split')->onKernelRequest($this->event(new Request()));
    }

    public function testSubRequestIsIgnored(): void
    {
        $request = new Request();
        $event = new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::SUB_REQUEST);
        $this->listener('banana-split')->onKernelRequest($event);

        $this->assertNull($request->attributes->get(NegotiateVersionListener::REQUEST_ATTRIBUTE));
    }
}
