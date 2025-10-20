<?php

declare(strict_types=1);

namespace App\Metrics;

use Artprima\PrometheusMetricsBundle\Metrics\MetricsCollectorInterface;
use Prometheus\CollectorRegistry;
use Prometheus\Exception\MetricsRegistrationException;

/**
 * Custom metrics collector for authentication service.
 *
 * Collects:
 * - Login attempts (success/failure with reasons)
 * - Kafka send failures
 */
final class MetricsCollector implements MetricsCollectorInterface
{
    private string $namespace;
    private CollectorRegistry $collectionRegistry;

    public function init(string $namespace, CollectorRegistry $collectionRegistry): void
    {
        $this->namespace = $namespace;

        $this->collectionRegistry = $collectionRegistry;
    }

    /**
     * Increment login attempt counter.
     *
     * @param string $status 'success' or 'failure'
     * @param string|null $reason failure reason: 'wrong_password', 'user_not_found', 'rate_limited', etc
     *
     * @throws MetricsRegistrationException
     */
    public function incrementLoginAttempts(string $status, ?string $reason = null): void
    {
        $labels = ['status' => $status];
        if (null !== $reason) {
            $labels['reason'] = $reason;
        }

        $counter = $this->collectionRegistry->getOrRegisterCounter(
            $this->namespace,
            'login_attempts_total',
            'Total number of login attempts',
            array_keys($labels)
        );

        $counter->inc(array_values($labels));
    }

    /**
     * Increment Kafka send failures counter.
     *
     * @throws MetricsRegistrationException
     */
    public function incrementKafkaFailures(): void
    {
        $counter = $this->collectionRegistry->getOrRegisterCounter(
            $this->namespace,
            'kafka_send_failures_total',
            'Total number of Kafka send failures'
        );

        $counter->inc();
    }
}
