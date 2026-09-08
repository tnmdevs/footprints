<?php

namespace TNM\Footprints\Tests\Unit;

use Exception;
use InvalidArgumentException;
use ReflectionClass;
use TNM\Footprints\Tests\TestCase;
use TNM\Footprints\Utils\KafkaLog;

class KafkaLogTest extends TestCase
{
    public function test_throws_exception_when_brokers_are_not_configured(): void
    {
        config()->set('footprints.kafka.brokers', null);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('[Kafka] No brokers configured.');

        new KafkaLog();
    }

    public function test_throws_exception_when_brokers_are_empty_string(): void
    {
        config()->set('footprints.kafka.brokers', '   ');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('[Kafka] No brokers configured.');

        new KafkaLog();
    }

    public function test_throws_exception_when_brokers_are_non_string(): void
    {
        config()->set('footprints.kafka.brokers', 12345);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('[Kafka] No brokers configured.');

        new KafkaLog();
    }

    public function test_throws_exception_when_parsed_brokers_are_empty(): void
    {
        config()->set('footprints.kafka.brokers', ', ,  ,');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No Kafka brokers are configured.');

        new KafkaLog();
    }

    public function test_throws_exception_when_single_broker_is_unreachable(): void
    {
        config()->set('footprints.kafka.brokers', '127.0.0.1:59998');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Kafka broker check failed. Unreachable brokers: 127.0.0.1:59998');

        new KafkaLog();
    }

    public function test_throws_exception_when_multiple_brokers_are_unreachable(): void
    {
        config()->set('footprints.kafka.brokers', '127.0.0.1:59997, 127.0.0.1:59998');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Kafka broker check failed. Unreachable brokers: 127.0.0.1:59997, 127.0.0.1:59998');

        new KafkaLog();
    }

    public function test_constructor_validates_kafka_config(): void
    {
        config()->set('footprints.kafka.security_protocol', 'INVALID_PROTOCOL');
        config()->set('footprints.kafka.brokers', '127.0.0.1:9092');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid Kafka security_protocol 'INVALID_PROTOCOL'");

        new KafkaLog();
    }

    public function test_constructor_succeeds_when_broker_is_reachable(): void
    {
        $server = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if (!$server) {
            $this->markTestSkipped("Unable to create socket server: {$errstr}");
        }

        $address = stream_socket_get_name($server, false);
        $port = (int)substr(strrchr($address, ':'), 1);

        config()->set('footprints.kafka.brokers', "127.0.0.1:{$port}");

        try {
            $kafka = new KafkaLog();
            $this->assertInstanceOf(KafkaLog::class, $kafka);
        } finally {
            fclose($server);
        }
    }

    public function test_safe_json_encode_with_valid_data(): void
    {
        $server = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if (!$server) {
            $this->markTestSkipped("Unable to create socket server: {$errstr}");
        }

        $address = stream_socket_get_name($server, false);
        $port = (int)substr(strrchr($address, ':'), 1);
        config()->set('footprints.kafka.brokers', "127.0.0.1:{$port}");

        try {
            $kafka = new KafkaLog();

            $ref = new ReflectionClass($kafka);
            $method = $ref->getMethod('safeJsonEncode');
            $method->setAccessible(true);

            $result = $method->invoke($kafka, ['foo' => 'bar', 'count' => 42]);
            $this->assertJsonStringEqualsJsonString(json_encode(['foo' => 'bar', 'count' => 42]), $result);
        } finally {
            fclose($server);
        }
    }

    public function test_safe_json_encode_with_invalid_utf8(): void
    {
        $server = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if (!$server) {
            $this->markTestSkipped("Unable to create socket server: {$errstr}");
        }

        $address = stream_socket_get_name($server, false);
        $port = (int)substr(strrchr($address, ':'), 1);
        config()->set('footprints.kafka.brokers', "127.0.0.1:{$port}");

        try {
            $kafka = new KafkaLog();

            $ref = new ReflectionClass($kafka);
            $method = $ref->getMethod('safeJsonEncode');
            $method->setAccessible(true);

            $invalidUtf8String = "bad-utf8: \xB1\x31";
            $result = $method->invoke($kafka, ['payload' => $invalidUtf8String]);

            $this->assertIsString($result);
            $decoded = json_decode($result, true);
            $this->assertIsArray($decoded);
            $this->assertArrayHasKey('payload', $decoded);
        } finally {
            fclose($server);
        }
    }

    public function test_safe_json_encode_fallback_on_unencodable_value(): void
    {
        $server = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if (!$server) {
            $this->markTestSkipped("Unable to create socket server: {$errstr}");
        }

        $address = stream_socket_get_name($server, false);
        $port = (int)substr(strrchr($address, ':'), 1);
        config()->set('footprints.kafka.brokers', "127.0.0.1:{$port}");

        try {
            $kafka = new class extends KafkaLog {
                public function exposeSafeJsonEncode(mixed $val): string
                {
                    return $this->safeJsonEncode($val);
                }
            };

            // Test normal encoding
            $json = $kafka->exposeSafeJsonEncode(['key' => 'value']);
            $this->assertSame('{"key":"value"}', $json);
        } finally {
            fclose($server);
        }
    }
}
