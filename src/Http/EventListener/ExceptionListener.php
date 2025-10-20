<?php

declare(strict_types=1);

namespace App\Http\EventListener;

use App\Exceptions\AuthException;
use App\Exceptions\InfrastructureException;
use App\Helper\ExceptionFormatter;
use App\Http\Response\ApiResponse;
use InvalidArgumentException;
use LogicException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

/**
 * We want to customize some exceptions since they are logical cases and not exceptions.
 * For example, some unicity constraints.
 */
final readonly class ExceptionListener
{
    public function __construct(
        private LoggerInterface $logger,
        private ExceptionFormatter $exceptionFormatter,
    ) {
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();

        if ($exception instanceof LogicException) {
            $status = $exception instanceof InvalidArgumentException ? 422 : 400;

            // LogicException = business logic error, expected behavior
            $this->logger->warning('Business logic error', [
                'exception' => $this->exceptionFormatter->format($exception, traceLimit: 5),
                'request' => [
                    'uri' => $request->getRequestUri(),
                    'method' => $request->getMethod(),
                ],
                'status' => $status,
            ]);

            $response = ApiResponse::error($exception->getMessage(), status: $status);

            $event->setResponse(new JsonResponse(
                $response->toArray(),
                $response->getStatus()
            ));
        }

        if ($exception instanceof AuthException) {
            // AuthException = authentication/authorization failure (security audit)
            $this->logger->info('Authentication failed', [
                'exception' => $this->exceptionFormatter->format($exception, traceLimit: 3),
                'request' => [
                    'uri' => $request->getRequestUri(),
                    'method' => $request->getMethod(),
                    'ip' => $request->getClientIp(),
                ],
            ]);

            $response = ApiResponse::error($exception->getMessage(), status: 401);

            $event->setResponse(new JsonResponse(
                $response->toArray(),
                $response->getStatus()
            ));
        }

        if ($exception instanceof InfrastructureException) {
            // InfrastructureException = infrastructure failure (Kafka, DB, etc.) - needs attention
            $this->logger->error('Infrastructure error', [
                'exception' => $this->exceptionFormatter->format($exception, traceLimit: 15),
                'request' => [
                    'uri' => $request->getRequestUri(),
                    'method' => $request->getMethod(),
                ],
            ]);

            $response = ApiResponse::error('Internal error, try later', status: 500);

            $event->setResponse(new JsonResponse(
                $response->toArray(),
                $response->getStatus()
            ));
        }
    }
}
