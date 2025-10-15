<?php

declare(strict_types=1);

namespace App\Http\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Protects /web/refresh and /web/logout endpoints from CSRF attacks.
 *
 * Ensures the request comes from a trusted origin and contains
 *  a custom header (X-Requested-With), preventing cross-site POSTs
 *  with attached cookies (refresh_id).
 */
#[AsEventListener(event: RequestEvent::class, method: 'onKernelRequest')]
final class AntiCsrfForRefreshSubscriber
{
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if ($request->isMethod('POST') && preg_match('#^/web/(refresh|logout)$#', $request->getPathInfo())) {
            $origin = $request->headers->get('Origin') ?? $request->headers->get('Referer');

            if (
                !$origin
                || !preg_match('#^https?://(localhost(:\d+)?|127\.0\.0\.1(:\d+)?|your\.prod\.domain)$#i', $origin)
            ) {
                throw new AccessDeniedHttpException('Invalid origin');
            }

            if ('XMLHttpRequest' !== $request->headers->get('X-Requested-With')) {
                throw new AccessDeniedHttpException('Missing CSRF header');
            }
        }
    }
}
