<?php

namespace TNM\Footprints\Utils;

use Exception;
use Illuminate\Support\Facades\Log;
use Throwable;
use function Laravel\Prompts\error;

class KafkaLog
{
    private array $config;

    /**
     * @throws Exception
     */
    public function __construct()
    {
        if (
            !extension_loaded('rdkafka') ||
            !class_exists(\RdKafka\Producer::class)
        ) {
            throw new Exception(
                'The RdKafka extension is not installed nor enabled.'
            );
        }

        $this->config = config('footprints.kafka', []);

        validateConfig();

        $brokerStr = $this->config['brokers'];

        if (!is_string($brokerStr) || '' === trim($brokerStr)) {
            throw new Exception(
                '[Kafka] No brokers configured.'
            );
        }

        $brokers = checkBrokers($brokerStr);

        if (empty($brokers)) {
            throw new Exception(
                'No Kafka brokers are configured.'
            );
        }

        $failedBrokers = array_keys(
            array_filter(
                $brokers,
                fn(bool $reachable) => !$reachable
            )
        );

        if (!empty($failedBrokers)) {
            throw new Exception(
                'Kafka broker check failed. Unreachable brokers: '
                . implode(', ', $failedBrokers)
            );
        }

        Log::debug('[Kafka] All configured brokers are reachable.');
    }


    public function log(array $footprint): void
    {
        $kafkaConf = new \RdKafka\Conf();

        $kafkaConf->set(
            'bootstrap.servers',
            $this->config['brokers']
        );

        $kafkaConf->set(
            'socket.timeout.ms',
            (string)$this->config['timeout_ms']
        );

        if (!empty($this->config['client_id'])) {
            $kafkaConf->set(
                'client.id',
                $this->config['client_id']
            );
        }

        if (!empty($this->config['security_protocol'])) {
            $kafkaConf->set(
                'security.protocol',
                $this->config['security_protocol']
            );
        }

        if (!empty($this->config['sasl_mechanism'])) {
            $kafkaConf->set(
                'sasl.mechanisms',
                $this->config['sasl_mechanism']
            );
        }

        if (!empty($this->config['sasl_username'])) {
            $kafkaConf->set(
                'sasl.username',
                $this->config['sasl_username']
            );
        }

        if (!empty($this->config['sasl_password'])) {
            $kafkaConf->set(
                'sasl.password',
                $this->config['sasl_password']
            );
        }

        $producer = new \RdKafka\Producer($kafkaConf);
        $topic = $producer->newTopic($this->config['topic']);

        try {
            $messageKey = $this->config['message_key_func']($footprint);
        } catch (Throwable $e) {
            Log::debug("User supplied message key func erred: {$e->getMessage()}");
            $messageKey = getDefaultEventKey($footprint);
        }

        $footprint["agent"] = "php-laravel";

        $message = $this->safeJsonEncode($footprint);

        $partition = RD_KAFKA_PARTITION_UA;
        $flags = 0;

        $topic->produce($partition, $flags, $message, $messageKey);

        $producer->poll(0);

        $result = $producer->flush(30000);

        if ($result !== RD_KAFKA_RESP_ERR_NO_ERROR) {
            Logger:
            error("Kafka failed to produce event with error code: {$result}");
        }
    }


    protected function safeJsonEncode(mixed $value): string
    {
        $encoded = json_encode(
            $value,
            JSON_INVALID_UTF8_SUBSTITUTE |
            JSON_PARTIAL_OUTPUT_ON_ERROR
        );

        if ($encoded === false) {
            return json_encode([
                'error' => 'JSON encoding failed',
                'message' => json_last_error_msg(),
            ]);
        }

        return $encoded;
    }
}
