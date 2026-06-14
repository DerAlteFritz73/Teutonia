<?php

namespace App\Middleware;

use App\Repository\BlacklistedIpRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;

class BlockBlacklistedIpMiddleware
{
    public function __construct(
        private BlacklistedIpRepository $blacklistedIpRepository,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $clientIp = $event->getRequest()->getClientIp();

        if ($clientIp && $this->blacklistedIpRepository->isBlacklisted($clientIp)) {
            $event->setResponse(new Response('Access Denied', Response::HTTP_FORBIDDEN));
        }
    }
}
