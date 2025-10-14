<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Infrastructure\Kafka\KafkaProducer;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Throwable;

/**
 * Possible statuses up, degraded, down.
 * Since this is a very important service, we will use only UP/DOWN.
 */
readonly class HealthController
{
    public function __construct(
        private Connection $db,
        private KafkaProducer $kafkaProducer,
    ) {
    }

    private const STATUS_UP = 'UP';
    private const STATUS_DOWN = 'DOWN';

    #[Route('/health', methods: ['GET'])]
    #[IsGranted('PUBLIC_ACCESS')]
    public function __invoke(): JsonResponse
    {
        $status = [
            'status' => self::STATUS_UP,
            'timestamp' => (new DateTimeImmutable())->format(DATE_ATOM),
            'checks' => [
                'database' => self::STATUS_UP,
                'kafka' => self::STATUS_UP,
            ],
        ];

        // 1. Check DB
        try {
            $this->db->executeQuery('SELECT 1');
        } catch (Throwable $exception) {
            $status['status'] = self::STATUS_DOWN;
            $status['checks']['database'] = self::STATUS_DOWN;
        }

        // 2. Check Kafka
        try {
            // Just check broker without any topic verification
            $this->kafkaProducer->send([
                'event' => 'health.check',
                'timestamp' => time(),
            ], 500);
        } catch (Throwable $exception) {
            $status['status'] = self::STATUS_DOWN;
            $status['checks']['kafka'] = self::STATUS_DOWN;
        }

        return new JsonResponse($status, $status['status'] === self::STATUS_UP ? 200 : 503);
    }
}
