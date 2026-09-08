<?php

namespace TNM\Footprints\Tests\Unit\Utils;

use Carbon\Carbon;
use TNM\Footprints\Tests\TestCase;
use function TNM\Footprints\Utils\getDefaultEventKey;

class DefaultEventKeyTest extends TestCase
{
    public function test_generates_default_event_key_with_valid_footprint(): void
    {
        $footprint = [
            'request_method' => 'POST',
            'request_time' => '2026-09-06T15:30:45.123Z',
        ];

        $key = getDefaultEventKey($footprint);

        $this->assertStringStartsWith('footprint:POST:', $key);
        $this->assertStringEndsWith(':20260906153045', $key);
        $this->assertMatchesRegularExpression('/^footprint:POST:[a-f0-9]{8}:20260906153045$/', $key);
    }

    public function test_uses_unknown_when_request_method_is_missing(): void
    {
        $footprint = [
            'request_time' => '2026-01-01 00:00:00',
        ];

        $key = getDefaultEventKey($footprint);

        $this->assertStringStartsWith('footprint:UNKNOWN:', $key);
        $this->assertMatchesRegularExpression('/^footprint:UNKNOWN:[a-f0-9]{8}:20260101000000$/', $key);
    }

    public function test_uses_unknown_when_request_method_is_empty_or_whitespace(): void
    {
        $this->assertStringStartsWith('footprint:UNKNOWN:', getDefaultEventKey(['request_method' => '']));
        $this->assertStringStartsWith('footprint:UNKNOWN:', getDefaultEventKey(['request_method' => '   ']));
    }

    public function test_uses_unknown_when_request_method_is_not_a_string(): void
    {
        $this->assertStringStartsWith('footprint:UNKNOWN:', getDefaultEventKey(['request_method' => 123]));
        $this->assertStringStartsWith('footprint:UNKNOWN:', getDefaultEventKey(['request_method' => null]));
        $this->assertStringStartsWith('footprint:UNKNOWN:', getDefaultEventKey(['request_method' => ['GET']]));
        $this->assertStringStartsWith('footprint:UNKNOWN:', getDefaultEventKey(['request_method' => true]));
    }

    public function test_handles_various_http_methods(): void
    {
        $methods = ['GET', 'PUT', 'DELETE', 'PATCH', 'OPTIONS', 'HEAD'];

        foreach ($methods as $method) {
            $key = getDefaultEventKey(['request_method' => $method, 'request_time' => '2026-01-01 12:00:00']);
            $this->assertStringStartsWith("footprint:{$method}:", $key);
        }
    }

    public function test_uses_now_when_request_time_is_missing(): void
    {
        $now = Carbon::create(2026, 9, 6, 12, 0, 0);
        Carbon::setTestNow($now);

        try {
            $key = getDefaultEventKey(['request_method' => 'GET']);
            $this->assertStringEndsWith(':20260906120000', $key);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_falls_back_to_now_when_request_time_is_invalid(): void
    {
        $now = Carbon::create(2026, 9, 6, 14, 20, 30);
        Carbon::setTestNow($now);

        try {
            $key = getDefaultEventKey([
                'request_method' => 'GET',
                'request_time' => 'invalid-non-parsable-date-string',
            ]);
            $this->assertStringEndsWith(':20260906142030', $key);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_generates_unique_keys_across_multiple_invocations(): void
    {
        $footprint = [
            'request_method' => 'POST',
            'request_time' => '2026-09-06 12:00:00',
        ];

        $key1 = getDefaultEventKey($footprint);
        $key2 = getDefaultEventKey($footprint);

        $this->assertNotEquals($key1, $key2);
    }
}
