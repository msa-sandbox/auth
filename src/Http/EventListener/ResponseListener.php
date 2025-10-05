<?php

declare(strict_types=1);

namespace App\Http\EventListener;

use App\Http\Response\ApiResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ViewEvent;

final class ResponseListener
{
    public function onKernelView(ViewEvent $event): void
    {
        /** @var ApiResponse|null $controllerResult */
        $controllerResult = $event->getControllerResult();

        if ($controllerResult instanceof ApiResponse) {
            $response = new JsonResponse(
                $controllerResult->toArray(),
                $controllerResult->getStatus()
            );

            $event->setResponse($response);
        }
    }
}
