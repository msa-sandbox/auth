<?php

declare(strict_types=1);

namespace App\Metrics;

use Artprima\PrometheusMetricsBundle\Metrics\MetricsCollectorInterface;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Prometheus\CollectorRegistry;
use Prometheus\Exception\MetricsRegistrationException;

/**
 * Collects gauge metric for active sessions count.
 *
 * Active session = not expired and not revoked.
 */
final class ActiveSessionsCollector implements MetricsCollectorInterface
{
    private string $namespace;
    private CollectorRegistry $collectionRegistry;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Initialize collector with namespace and registry.
     *
     * @throws MetricsRegistrationException
     */
    public function init(string $namespace, CollectorRegistry $collectionRegistry): void
    {
        $this->namespace = $namespace;
        $this->collectionRegistry = $collectionRegistry;

        // Pre-register the gauge
        $this->collectionRegistry->getOrRegisterGauge(
            $this->namespace,
            'active_sessions',
            'Number of active refresh sessions (not expired and not revoked)'
        );
    }

    /**
     * Collect active sessions count on each Prometheus scrape.
     *
     * @return array
     *
     * @throws MetricsRegistrationException
     */
    public function collect(): array
    {
        $gauge = $this->collectionRegistry->getOrRegisterGauge(
            $this->namespace,
            'active_sessions',
            'Number of active refresh sessions (not expired and not revoked)'
        );

        $count = $this->getActiveSessionsCount();
        $gauge->set($count);

        return [];
    }

    /**
     * Count active sessions from database.
     */
    private function getActiveSessionsCount(): int
    {
        $query = $this->em->createQuery(
            'SELECT COUNT(r.id) FROM App\Entity\RefreshSession r
             WHERE r.expires_at > :now AND r.revoked = :revoked'
        );
        $query->setParameter('now', new DateTimeImmutable());
        $query->setParameter('revoked', false);

        return (int) $query->getSingleScalarResult();
    }
}
