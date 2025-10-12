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

readonly class HealthController
{
    public function __construct(
        private Connection $db,
        private KafkaProducer $kafkaProducer,
    ) {
    }

    #[Route('/health', methods: ['GET'])]
    #[IsGranted('PUBLIC_ACCESS')]
    public function __invoke(): JsonResponse
    {
        $status = [
            'status' => 'ok',
            'timestamp' => (new DateTimeImmutable())->format(DATE_ATOM),
            'checks' => [
                'database' => 'ok',
                'kafka' => 'ok',
            ],
        ];

        // 1. Check DB
        try {
            $this->db->executeQuery('SELECT 1');
        } catch (Throwable $e) {
            $status['status'] = 'degraded';
            $status['checks']['database'] = 'fail: ' . $e->getMessage();
        }

        // 2. Check Kafka
        try {
            // Just check broker without any topic verification
            $this->kafkaProducer->send([
                'event' => 'health.check',
                'timestamp' => time(),
            ]);
        } catch (Throwable $e) {
            $status['status'] = 'degraded';
            $status['checks']['kafka'] = 'fail: ' . $e->getMessage();
        }

        return new JsonResponse($status, $status['status'] === 'ok' ? 200 : 503);
    }
}
