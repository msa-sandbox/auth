<?php

declare(strict_types=1);

namespace App\Tests\Stub;

use App\Metrics\MetricsCollector;
use Psr\Log\LoggerInterface;

/**
 * Stub for KafkaProducer to use in tests where Kafka is not available
 */
readonly class StubKafkaProducer
{
    public function __construct(
        string $brokers,
        string $topicName,
        private LoggerInterface $logger,
        private MetricsCollector $metricsCollector,
    ) {
        // Do nothing - stub implementation
    }

    public function send(array $payload, int $conformationTime = 2000): void
    {
        // Do nothing - stub implementation
        $this->logger->debug('StubKafkaProducer: Message not sent (stub)', ['payload' => $payload]);
    }
}
