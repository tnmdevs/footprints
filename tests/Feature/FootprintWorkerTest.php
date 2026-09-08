<?php

namespace TNM\Footprints\Tests\Feature;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use TNM\Footprints\Jobs\FootprintWorker;
use TNM\Footprints\Tests\TestCase;

class FootprintWorkerTest extends TestCase
{
    private array $sampleFootprint;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sampleFootprint = [
            'request_id' => 'req-12345-abc',
            'app_name' => 'test-application',
            'app_environment' => 'testing',
            'request_method' => 'POST',
            'request_uri' => 'api/v1/checkout',
            'request_url' => 'https://api.example.com/api/v1/checkout',
            'request_time' => '2026-09-06T12:00:00.000Z',
            'request_headers' => ['content-type' => ['application/json'], 'authorization' => ['[REDACTED]']],
            'request_body' => ['item_id' => 99, 'amount' => 500],
            'response_status_code' => 200,
            'response_success' => true,
            'response_headers' => ['content-type' => ['application/json']],
            'response_body' => '{"status":"ok"}',
            'duration_ms' => 45.5,
            'client_ip' => '192.168.1.50',
            'host_ip' => '127.0.0.1',
            'host_name' => 'web-server-01',
            'user_id' => 'user-42',
            'user_type' => 'App\\Models\\User',
            'exception_message' => null,
        ];
    }

    public function test_implements_should_queue(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new FootprintWorker($this->sampleFootprint));
    }

    public function test_falls_back_to_database_when_kafka_fails(): void
    {
        // Broker string empty forces immediate Kafka failure without socket timeout delay
        config()->set('footprints.kafka.brokers', '');

        Log::shouldReceive('error')
            ->once()
            ->withArgs(fn(string $msg) => str_contains($msg, 'Failed to handle footprint job:'));

        Log::shouldReceive('debug')
            ->once()
            ->with('Logging footprint to database now...');

        $worker = new FootprintWorker($this->sampleFootprint);
        $worker->handle();

        $record = DB::table('application_footprints')->where('request_id', 'req-12345-abc')->first();

        $this->assertNotNull($record);
        $this->assertSame('test-application', $record->app_name);
        $this->assertSame('testing', $record->app_environment);
        $this->assertSame('POST', $record->request_method);
        $this->assertSame('api/v1/checkout', $record->request_uri);
        $this->assertSame('https://api.example.com/api/v1/checkout', $record->request_url);
        $this->assertSame('2026-09-06T12:00:00.000Z', $record->request_time);

        $this->assertSame($this->sampleFootprint['request_headers'], json_decode($record->request_headers, true));
        $this->assertSame($this->sampleFootprint['request_body'], json_decode($record->request_body, true));

        $this->assertEquals(200, $record->response_status_code);
        $this->assertTrue((bool)$record->response_success);
        $this->assertSame('{"status":"ok"}', $record->response_body);
        $this->assertEquals(45.5, (float)$record->duration_ms);
        $this->assertSame('192.168.1.50', $record->client_ip);
        $this->assertSame('127.0.0.1', $record->host_ip);
        $this->assertSame('web-server-01', $record->host_name);
        $this->assertSame('user-42', $record->user_id);
        $this->assertSame('App\\Models\\User', $record->user_type);

        $this->assertStringContainsString('[Kafka] No brokers configured', $record->exception_message);
    }

    public function test_fail_method_inserts_exception_into_database(): void
    {
        $worker = new FootprintWorker($this->sampleFootprint);
        $worker->fail(new RuntimeException('Queue worker failed unexpectedly'));

        $record = DB::table('application_footprints')->where('request_id', 'req-12345-abc')->first();

        $this->assertNotNull($record);
        $this->assertSame('Queue worker failed unexpectedly', $record->exception_message);
    }

    public function test_fallback_preserves_existing_exception_message_in_footprint(): void
    {
        config()->set('footprints.kafka.brokers', '');

        $footprint = $this->sampleFootprint;
        $footprint['exception_message'] = 'Original Controller Exception';

        $worker = new FootprintWorker($footprint);
        $worker->handle();

        $record = DB::table('application_footprints')->where('request_id', 'req-12345-abc')->first();

        $this->assertNotNull($record);
        $this->assertSame('Original Controller Exception', $record->exception_message);
    }

    public function test_uses_custom_table_name(): void
    {
        config()->set('footprints.table_name', 'custom_footprints');
        config()->set('footprints.kafka.brokers', '');

        // run migration for custom table name
        $migration = include __DIR__ . '/../../database/migrations/create_v3_footprints_table.php';
        $migration->up();

        $worker = new FootprintWorker($this->sampleFootprint);
        $worker->handle();

        $record = DB::table('custom_footprints')->where('request_id', 'req-12345-abc')->first();
        $this->assertNotNull($record);
    }
}
