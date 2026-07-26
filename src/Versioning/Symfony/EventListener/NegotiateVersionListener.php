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

namespace ApiPlatform\Versioning\Symfony\EventListener;

use ApiPlatform\Metadata\Exception\BadRequestException;
use ApiPlatform\Versioning\Exception\OutOfRangeVersionException;
use ApiPlatform\Versioning\State\VersionNegotiator;
use ApiPlatform\Versioning\State\VersionResolverInterface;
use ApiPlatform\Versioning\Version\VersionGraph;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Negotiates the requested version once, on the main request, and stashes it on
 * the request for the serializer-context builder and the response listener.
 *
 * Doing it here (rather than in the serializer context builder) means an
 * out-of-range version throws a 400 that the error handler can render — the
 * error response is serialized without re-triggering negotiation.
 *
 * @experimental
 */
final class NegotiateVersionListener
{
    /**
     * Request attribute holding the negotiated version.
     */
    public const REQUEST_ATTRIBUTE = '_api_platform_version';

    public function __construct(
        private readonly VersionResolverInterface $resolver,
        private readonly VersionNegotiator $negotiator,
        private readonly VersionGraph $graph,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        try {
            $version = $this->negotiator->negotiate($this->resolver->resolve($request), $this->graph);
        } catch (OutOfRangeVersionException $e) {
            throw new BadRequestException($e->getMessage(), 0, $e);
        }

        $request->attributes->set(self::REQUEST_ATTRIBUTE, $version);
    }
}
