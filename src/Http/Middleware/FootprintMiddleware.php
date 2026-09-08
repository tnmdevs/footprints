<?php

namespace TNM\Footprints\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Throwable;
use TNM\Footprints\Jobs\FootprintWorker;

final class FootprintMiddleware
{
    private const REQUEST_ID_HEADERS = [
        'x-request-id',
        'request-id',
        'id',
        'requestid',
    ];

    public function handle(Request $request, Closure $next)
    {
        if (!defined('LARAVEL_START')) {
            define('LARAVEL_START', microtime(true));
        }

        return $next($request);
    }

    public function terminate(Request $request, $response): void
    {
        if (!config("footprints.enabled", true)) {
            Log::debug('Footprint capture is disabled. Skipping...');
            return;
        }

        try {
            $data = $this->collectData($request, $response);

            FootprintWorker::dispatch($data)
                ->onConnection(config("footprints.queue.connection"))
                ->onQueue(config("footprints.queue.name"));
        } catch (Throwable $e) {
            Log::error("Error while dispatching footprint logging job: " . $e->getMessage());
        }

    }

    protected function collectData(Request $request, $response): array
    {
        return [
            'request_id' => $this->getRequestId($request),
            'app_name' => config('footprints.app_name', config('app.name', 'unnamed-laravel-app')),
            'app_environment' => config('app.env'),

            // Request Group
            'request_method' => $request->method(),
            'request_uri' => $request->path(),
            'request_url' => $request->fullUrl(),
            'request_time' => gmdate('Y-m-d\TH:i:s.v\Z', (float)LARAVEL_START),
            'request_headers' => $this->maskInputs($request->headers->all()),
            'request_body' => $this->maskInputs($this->cleanInputs($request->all())),

            // Response Group
            'response_status_code' => $response->getStatusCode(),
            'response_success' => $response->isSuccessful(),
            'response_headers' => $response->headers->all(),
            'response_body' => $this->getResponseContent($response),

            // Processing & Host Data
            'duration_ms' => (float)$this->getTurnAroundTime(),
            'client_ip' => $request->ip(),
            'host_ip' => $request->server('SERVER_ADDR', '127.0.0.1'),
            'host_name' => gethostname(),

            // User Data
            'user_id' => $request->user() ? (string)$request->user()->getAuthIdentifier() : null,
            'user_type' => $request->user() ? get_class($request->user()) : null,

            'exception_message' => isset($response->exception) ? $response->exception->getMessage() : null,
        ];
    }

    protected function cleanInputs(mixed $data): mixed
    {
        if (!is_array($data)) {
            return $data;
        }

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->cleanInputs($value);
            } elseif ($value instanceof UploadedFile) {
                $data[$key] = "[File: " . $value->getClientOriginalName() . "]";
            }
        }
        return $data;
    }

    protected function maskInputs(mixed $data): mixed
    {
        if (!is_array($data)) {
            return $data;
        }

        $masks = config('footprints.mask_fields', []);

        if (is_string($masks)) {
            $masks = explode(',', $masks);
        }

        $masks = array_map(fn($m) => strtolower(trim($m)), $masks);

        foreach ($data as $key => $value) {
            $normalizedKey = strtolower(str_replace('-', '_', $key));

            if (in_array($normalizedKey, $masks)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = $this->maskInputs($value);
            }
        }
        return $data;
    }

    protected function getResponseContent($response)
    {
        if (!$response) {
            return null;
        }

        $contentType = $response->headers->get('Content-Type');

        if (Str::startsWith($contentType, ['text/', 'application/json', 'application/xml'])) {
            return $response->getContent();
        }
        return '(binary/stream content)';
    }

    private function getTurnAroundTime(): float|int
    {
        return round(round(microtime(true) - LARAVEL_START, 4) * 1000);
    }

    private function getRequestId(Request $request): ?string
    {
        foreach ($request->headers->all() as $key => $values) {
            if (!in_array(strtolower($key), self::REQUEST_ID_HEADERS, true)) {
                continue;
            }

            $value = $values[0] ?? null;

            if ($value !== null && $value !== '') {
                return (string)$value;
            }
        }

        return null;
    }
}