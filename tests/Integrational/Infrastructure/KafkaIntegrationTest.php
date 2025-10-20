<?php

declare(strict_types=1);

namespace App\Tests\Integrational\Infrastructure;

use App\Infrastructure\Kafka\KafkaProducer;
use App\Metrics\MetricsCollector;
use DateTimeImmutable;
use Prometheus\CollectorRegistry;
use Prometheus\Storage\InMemory;
use Psr\Log\NullLogger;
use RdKafka\Conf;
use RdKafka\KafkaConsumer;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Integration test verifying that a real Kafka broker is reachable
 * and that messages published by KafkaProducer can be consumed
 * from the expected topic.
 *
 * This test assumes a running Kafka instance and a topic
 * defined in the environment variable `KAFKA_BROKERS`.
 * It will be skipped automatically if Kafka is not reachable.
 */
final class KafkaIntegrationTest extends KernelTestCase
{
    private const TOPIC = 'user-events-test';

    /**
     * Publishes a message to Kafka using the application's KafkaProducer
     * and consumes it back using a real KafkaConsumer.
     *
     * The test asserts that:
     *  - The Kafka broker is reachable.
     *  - The message is successfully sent and can be read back
     *    from the configured test topic within a timeout window.
     */
    public function testKafkaProducesAndConsumesMessage(): void
    {
        $brokers = $_ENV['KAFKA_BROKERS'];

        // Skip test if Kafka broker is not reachable
        if (!$this->isKafkaReachable($brokers)) {
            self::markTestSkipped("Kafka is not reachable at $brokers");
        }

        // Prepare a unique payload for this test run
        $payload = [
            'event' => 'user.role.changed',
            'user_id' => 123,
            'new_roles' => ['ROLE_ADMIN'],
            'changed_at' => (new DateTimeImmutable())->format(DATE_ATOM),
            'test_id' => Uuid::v4()->toRfc4122(), // unique identifier for message matching
        ];

        // Produce a message into Kafka
        $collectorRegistry = new CollectorRegistry(new InMemory());
        $metricsCollector = new MetricsCollector($collectorRegistry);
        $producer = new KafkaProducer($brokers, self::TOPIC, new NullLogger(), $metricsCollector);
        $producer->send($payload);

        // Configure Kafka consumer to read from the same topic
        $conf = new Conf();
        $conf->set('metadata.broker.list', $brokers);
        $conf->set('group.id', 'phpunit-kafka-test-'.uniqid());
        $conf->set('auto.offset.reset', 'earliest');

        $consumer = new KafkaConsumer($conf);
        $consumer->subscribe([self::TOPIC]);

        // Try to consume the message for up to 10 seconds
        $found = false;
        $start = microtime(true);

        while (microtime(true) - $start < 10) {
            $message = $consumer->consume(1000);

            if (RD_KAFKA_RESP_ERR_NO_ERROR === $message->err) {
                $decoded = json_decode($message->payload, true, 512, JSON_THROW_ON_ERROR);
                if (($decoded['test_id'] ?? null) === $payload['test_id']) {
                    $found = true;
                    break;
                }
            }
        }

        // Assert that our message was successfully consumed
        self::assertTrue($found, 'Kafka message was not consumed within timeout');
    }

    /**
     * Checks whether the Kafka broker is reachable via TCP.
     *
     * @param string $brokers e.g. "kafka:9092" or "localhost:9092"
     */
    private function isKafkaReachable(string $brokers): bool
    {
        $host = explode(':', $brokers)[0];
        $port = (int) (explode(':', $brokers)[1] ?? 9092);

        $conn = @fsockopen($host, $port, $errno, $errstr, 1.0);
        if (false === $conn) {
            return false;
        }
        fclose($conn);

        return true;
    }
}
