<?php

namespace TNM\Footprints\Tests\Unit\Utils;

use Illuminate\Support\Facades\Log;
use TNM\Footprints\Tests\TestCase;
use function TNM\Footprints\Utils\checkBrokers;
use function TNM\Footprints\Utils\checkKafkaHost;

class CheckKafkaHostTest extends TestCase
{
    public function test_returns_true_when_host_is_reachable(): void
    {
        $server = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if (!$server) {
            $this->markTestSkipped("Could not create local test socket server: {$errstr} ({$errno})");
        }

        $address = stream_socket_get_name($server, false);
        $port = (int)substr(strrchr($address, ':'), 1);

        Log::shouldReceive('debug')
            ->once()
            ->withArgs(function (string $message) use ($port) {
                return str_contains($message, '[Kafka OK]') && str_contains($message, (string)$port);
            });

        try {
            $result = checkKafkaHost('127.0.0.1', $port);
            $this->assertTrue($result);
        } finally {
            fclose($server);
        }
    }

    public function test_returns_false_when_host_is_unreachable(): void
    {
        // Pick an ephemeral port and ensure it is closed
        $server = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($server) {
            $address = stream_socket_get_name($server, false);
            $port = (int)substr(strrchr($address, ':'), 1);
            fclose($server);
        } else {
            $port = 59199;
        }

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function (string $message) use ($port) {
                return str_contains($message, '[Kafka FAIL]') && str_contains($message, (string)$port);
            });

        $result = checkKafkaHost('127.0.0.1', $port);
        $this->assertFalse($result);
    }

    public function test_check_brokers_with_reachable_and_unreachable_hosts(): void
    {
        $server = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if (!$server) {
            $this->markTestSkipped("Could not create local test socket server: {$errstr} ({$errno})");
        }

        $address = stream_socket_get_name($server, false);
        $livePort = (int)substr(strrchr($address, ':'), 1);

        $deadPort = $livePort + 1 > 65535 ? $livePort - 1 : $livePort + 1;

        Log::shouldReceive('debug')
            ->atLeast()->once()
            ->withArgs(fn(string $msg) => str_contains($msg, '[Kafka OK]'));

        Log::shouldReceive('error')
            ->atLeast()->once()
            ->withArgs(fn(string $msg) => str_contains($msg, '[Kafka FAIL]'));

        try {
            $brokerStr = "127.0.0.1:{$livePort}, 127.0.0.1:{$deadPort}";
            $results = checkBrokers($brokerStr);

            $this->assertCount(2, $results);
            $this->assertTrue($results["127.0.0.1:{$livePort}"]);
            $this->assertFalse($results["127.0.0.1:{$deadPort}"]);
        } finally {
            fclose($server);
        }
    }
}
