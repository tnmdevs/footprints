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
            'method' => $request->method(),
            'request' => json_encode($request->except($this->getHiddenFields())),
            'content' => $request->getContent(),
            'response' => method_exists($response, 'content') ? $response->content() : null,
            'milliseconds' => $this->getTurnAroundTime(),
            'status' => method_exists($response, 'status') ? $response->status() : null,
            'success' => $response->isSuccessful(),
        ]);
    }

    private function getHiddenFields(): array
    {
        return ['password', 'pin', 'new_pin'];
    }

    private function getTurnAroundTime(): float|int
    {
        return round(microtime(true) - LARAVEL_START, 4) * 1000;
    }
}