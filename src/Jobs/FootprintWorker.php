<?php

namespace TNM\Footprints\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use TNM\Footprints\Utils\KafkaLog;

class FootprintWorker implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public array $footprint)
    {

    }

    public function fail(Throwable $exception = null): void
    {
        $this->logToDatabase($exception->getMessage());
    }

    public function handle(): void
    {
        try {
            $kafka = new KafkaLog();

            $kafka->log($this->footprint);
        } catch (Throwable $e) {
            Log::error("Failed to handle footprint job: " . $e->getMessage());
            $this->logToDatabase($e->getMessage());
        }
    }

    private function logToDatabase(string $error): void
    {
        Log::debug("Logging footprint to database now...");

        DB::connection()->table(config("footprints.table_name"))->insert([
            'request_id' => $this->footprint['request_id'],
            'app_name' => $this->footprint['app_name'],
            'app_environment' => $this->footprint['app_environment'],

            'request_method' => $this->footprint['request_method'],
            'request_uri' => $this->footprint['request_uri'],
            'request_url' => $this->footprint['request_url'],
            'request_time' => $this->footprint['request_time'],

            'request_headers' => json_encode(
                $this->footprint['request_headers'],
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            ),
            'request_body' => json_encode(
                $this->footprint['request_body'],
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            ),

            'response_status_code' => $this->footprint['response_status_code'],
            'response_success' => $this->footprint['response_success'],

            'response_headers' => json_encode(
                $this->footprint['response_headers'],
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            ),

            'response_body' => $this->footprint['response_body'],

            'duration_ms' => $this->footprint['duration_ms'],

            'client_ip' => $this->footprint['client_ip'],
            'host_ip' => $this->footprint['host_ip'],
            'host_name' => $this->footprint['host_name'],

            'user_id' => $this->footprint['user_id'],
            'user_type' => $this->footprint['user_type'],

            'exception_message' => $this->footprint['exception_message'] ?? $error,
        ]);
    }

}