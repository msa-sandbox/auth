<?php

declare(strict_types=1);

namespace App\Command;

use RdKafka\Conf;
use RdKafka\Exception;
use RdKafka\KafkaConsumer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function pcntl_signal;

#[AsCommand(
    name: 'kafka:consume',
    description: 'Reads messages from a Kafka topic',
)]
final class KafkaConsumeCommand extends Command
{
    public function __construct(
        private readonly string $brokers,
        private readonly string $topicName,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Listen system signals (Ctrl+C etc)
        declare(ticks=1);
        $stop = false;
        pcntl_signal(SIGINT, function () use (&$stop, $output): void {
            $output->writeln("\n<info>Stopping consumer...</info>");
            $stop = true;
        });

        // config Kafka Consumer
        $conf = new Conf();
        $conf->set('metadata.broker.list', $this->brokers);
        $conf->set('group.id', 'symfony-consumer-auth-command');
        $conf->set('auto.offset.reset', 'earliest'); // Consume from beginning if no offset saved
        $conf->set('enable.auto.commit', 'false');   // We will commit manually

        $consumer = new KafkaConsumer($conf);
        try {
            $consumer->subscribe([$this->topicName]);

            $output->writeln("<info>Connected to Kafka: {$this->brokers}</info>");
            $output->writeln("<info>Subscribed to topic: {$this->topicName}</info>");
            $output->writeln('<comment>Press Ctrl+C to stop.</comment>');
        } catch (Exception $e) {
            $output->writeln('<error>Failed to subscribe to topic: '.$e->getMessage().'</error>');

            return Command::FAILURE;
        }

        // Read messages
        while (!$stop) {
            try {
                $message = $consumer->consume(2000); // 2 sec waiting

                switch ($message->err) {
                    case RD_KAFKA_RESP_ERR_NO_ERROR:
                        $output->writeln(
                            sprintf(
                                '<comment>[%s:%d]</comment> %s',
                                $message->topic_name,
                                $message->partition,
                                $message->payload
                            )
                        );

                        // Confirm successful processing
                        $consumer->commitAsync($message);
                        break;

                    case RD_KAFKA_RESP_ERR__PARTITION_EOF:
                        // End of partition. Just wait for more messages
                        break;

                    case RD_KAFKA_RESP_ERR__TIMED_OUT:
                        // Timeout. Just wait for more messages
                        break;

                    default:
                        $output->writeln(
                            sprintf('<error>Kafka error [%d]: %s</error>', $message->err, $message->errstr())
                        );

                        break;
                }
            } catch (Exception $e) {
                $output->writeln('<error>Kafka error: '.$e->getMessage().'</error>');
            }
        }

        $output->writeln('<info>Consumer stopped gracefully.</info>');

        return Command::SUCCESS;
    }
}
