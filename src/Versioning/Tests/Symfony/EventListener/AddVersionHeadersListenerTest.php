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

use ApiPlatform\Versioning\State\VersionHeadersFactory;
use ApiPlatform\Versioning\State\VersionResolverInterface;
use ApiPlatform\Versioning\Symfony\EventListener\AddVersionHeadersListener;
use ApiPlatform\Versioning\Symfony\State\VersionSerializerContextBuilder;
use ApiPlatform\Versioning\Version\VersionGraph;
use ApiPlatform\Versioning\Version\VersionStep;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class AddVersionHeadersListenerTest extends TestCase
{
    private function listener(): AddVersionHeadersListener
    {
        $graph = VersionGraph::fromSteps([
            new VersionStep('cherry', 'banana'),
            new VersionStep('banana', 'apple'),
        ], 'cherry');

        $resolver = new class implements VersionResolverInterface {
            public function resolve(Request $request): ?string
            {
                return null;
            }

            public function getVary(): ?string
            {
                return 'Accept-Version';
            }
        };

        return new AddVersionHeadersListener(new VersionHeadersFactory($graph), $resolver);
    }

    private function event(Request $request): ResponseEvent
    {
        $kernel = $this->createStub(HttpKernelInterface::class);

        return new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, new Response());
    }

    public function testAddsDiscoverabilityAndVaryHeadersWhenVersionNegotiated(): void
    {
        $request = new Request();
        $request->attributes->set(VersionSerializerContextBuilder::REQUEST_ATTRIBUTE, 'banana');

        $event = $this->event($request);
        $this->listener()->onKernelResponse($event);
        $headers = $event->getResponse()->headers;

        $this->assertSame('banana', $headers->get('Content-Version'));
        $this->assertSame('cherry, banana, apple', $headers->get('API-Supported-Versions'));
        $this->assertStringContainsString('Accept-Version', (string) $headers->get('Vary'));
    }

    public function testDoesNothingWithoutNegotiatedVersion(): void
    {
        $event = $this->event(new Request());
        $this->listener()->onKernelResponse($event);

        $this->assertFalse($event->getResponse()->headers->has('Content-Version'));
    }
}
