<?php

namespace TNM\Footprints\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use TNM\Footprints\Models\Footprint;

class WriteFootprints
{
    public function handle(Request $request, Closure $next)
    {
        if (!defined('LARAVEL_START')) define('LARAVEL_START', microtime(true));

        return $next($request);
    }

    public function terminate(Request $request, $response): void
    {
        Footprint::create([
            'endpoint' => $request->route() ? $request->route()->uri : '',
            'uri' => $request->getRequestUri(),
            'user_type' => $request->user() ? get_class($request->user()) : null,
            'user_id' => auth()?->id(),
            'method' => $request->method(),
            'ip_address' => $request->ip(),
            'request' => json_encode($this->maskHiddenFields($request->all())),
            'request_headers' => json_encode($request->headers->all()),
            'content' => empty($request->all()) ? $request->getContent() : null,
            'response' => method_exists($response, 'content') ? $response->content() : null,
            'milliseconds' => $this->getTurnAroundTime(),
            'status' => method_exists($response, 'status') ? $response->status() : null,
            'success' => $response->isSuccessful(),
        ]);
    }

    private function maskHiddenFields(array $request): array
    {
        $hiddenFields = $this->getHiddenFields();
        $mask = [];
        foreach ($request as $key => $value) {
            if (in_array($key, $hiddenFields,)) {
                $value = '{{secret}}';
            }
            $mask[$key] = $value;
        }
        return $mask;
    }

    private function getHiddenFields(): array
    {
        return explode(',', config('footprints.hidden_fields'));
    }

    private function getTurnAroundTime(): float|int
    {
        return round(round(microtime(true) - LARAVEL_START, 4) * 1000);
    }
}