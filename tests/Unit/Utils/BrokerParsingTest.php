<?php

namespace TNM\Footprints\Tests\Unit\Utils;

use Illuminate\Support\Facades\Log;
use TNM\Footprints\Tests\TestCase;
use function TNM\Footprints\Utils\checkBrokers;
use function TNM\Footprints\Utils\parseBroker;

class BrokerParsingTest extends TestCase
{
    public function test_parses_ipv4_broker(): void
    {
        [$host, $port] = parseBroker('127.0.0.1:9092');
        $this->assertEquals('127.0.0.1', $host);
        $this->assertEquals(9092, $port);
    }

    public function test_parses_hostname_broker(): void
    {
        [$host, $port] = parseBroker('kafka.service.internal:9093');
        $this->assertEquals('kafka.service.internal', $host);
        $this->assertEquals(9093, $port);
    }

    public function test_parses_ipv6_broker(): void
    {
        [$host, $port] = parseBroker('[::1]:9092');
        $this->assertEquals('::1', $host);
        $this->assertEquals(9092, $port);

        [$host2, $port2] = parseBroker('[2001:0db8:85a3:0000:0000:8a2e:0370:7334]:9094');
        $this->assertEquals('2001:0db8:85a3:0000:0000:8a2e:0370:7334', $host2);
        $this->assertEquals(9094, $port2);
    }

    public function test_parses_broker_with_surrounding_whitespace(): void
    {
        [$host, $port] = parseBroker("   10.0.0.1:9092 \t\n ");
        $this->assertEquals('10.0.0.1', $host);
        $this->assertEquals(9092, $port);
    }

    public function test_returns_null_for_empty_or_whitespace_broker(): void
    {
        $this->assertSame([null, null], parseBroker(''));
        $this->assertSame([null, null], parseBroker('   '));
    }

    public function test_returns_null_when_colon_is_missing(): void
    {
        $this->assertSame([null, null], parseBroker('localhost'));
    }

    public function test_returns_null_when_host_is_empty(): void
    {
        $this->assertSame([null, null], parseBroker(':9092'));
        $this->assertSame([null, null], parseBroker('  :9092'));
    }

    public function test_returns_null_when_port_is_empty(): void
    {
        $this->assertSame([null, null], parseBroker('localhost:'));
        $this->assertSame([null, null], parseBroker('localhost:  '));
    }

    public function test_returns_null_when_port_is_non_numeric(): void
    {
        $this->assertSame([null, null], parseBroker('localhost:port'));
        $this->assertSame([null, null], parseBroker('localhost:9092a'));
    }

    public function test_returns_null_when_port_is_out_of_bounds(): void
    {
        $this->assertSame([null, null], parseBroker('localhost:0'));
        $this->assertSame([null, null], parseBroker('localhost:-1'));
        $this->assertSame([null, null], parseBroker('localhost:65536'));
        $this->assertSame([null, null], parseBroker('localhost:100000'));
    }

    public function test_accepts_boundary_ports(): void
    {
        $this->assertSame(['localhost', 1], parseBroker('localhost:1'));
        $this->assertSame(['localhost', 65535], parseBroker('localhost:65535'));
    }

    public function test_check_brokers_returns_empty_array_for_empty_string(): void
    {
        $this->assertSame([], checkBrokers(''));
        $this->assertSame([], checkBrokers(' , ,   '));
    }

    public function test_check_brokers_handles_invalid_broker_format_and_logs_error(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->with('[Kafka] Invalid broker configuration: invalid-broker');

        $results = checkBrokers('invalid-broker');

        $this->assertArrayHasKey('invalid-broker', $results);
        $this->assertFalse($results['invalid-broker']);
    }
}
