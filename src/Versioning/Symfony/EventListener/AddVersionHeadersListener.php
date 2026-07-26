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

use ApiPlatform\Versioning\State\VersionHeadersFactory;
use ApiPlatform\Versioning\State\VersionResolverInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Adds the discoverability headers (Content-Version, API-Supported-Versions)
 * and the cache Vary header once a version has been negotiated on the request.
 *
 * @experimental
 */
final class AddVersionHeadersListener
{
    public function __construct(
        private readonly VersionHeadersFactory $headersFactory,
        private readonly VersionResolverInterface $resolver,
    ) {
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $version = $event->getRequest()->attributes->get(NegotiateVersionListener::REQUEST_ATTRIBUTE);
        if (!\is_string($version)) {
            return;
        }

        $response = $event->getResponse();
        foreach ($this->headersFactory->create($version) as $name => $value) {
            $response->headers->set($name, $value);
        }

        if (null !== ($vary = $this->resolver->getVary())) {
            $response->setVary($vary, false);
        }
    }
}
