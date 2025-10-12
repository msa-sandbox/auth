<?php

declare(strict_types=1);

namespace App\Infrastructure\Kafka;

use Psr\Log\LoggerInterface;
use RdKafka\Conf;
use RdKafka\Producer;
use RdKafka\ProducerTopic;
use RuntimeException;
use Throwable;

readonly class KafkaProducer
{
    private Producer $producer;
    private ProducerTopic $topic;

    public function __construct(
        string $brokers,
        string $topicName,
        private LoggerInterface $logger,
    ) {
        $conf = new Conf();
        $conf->set('metadata.broker.list', $brokers);

        $this->producer = new Producer($conf);
        $this->topic = $this->producer->newTopic($topicName);
    }

    /**
     * @param array $payload
     *
     * @return void
     */
    public function send(array $payload): void
    {
        try {
            $json = json_encode($payload, JSON_THROW_ON_ERROR);
            $this->topic->produce(RD_KAFKA_PARTITION_UA, 0, $json);

            // Wait for confirmation
            $result = $this->producer->flush(10000); // up to 10 seconds
            if ($result !== RD_KAFKA_RESP_ERR_NO_ERROR) {
                throw new RuntimeException('Kafka flush failed: ' . $result);
            }

            $this->logger->info('Kafka message sent', ['payload' => $payload]);
        } catch (Throwable $e) {
            $this->logger->error('Kafka publish failed', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);
            throw new RuntimeException('Failed to send message to Kafka', 0, $e);
        }
    }
}
