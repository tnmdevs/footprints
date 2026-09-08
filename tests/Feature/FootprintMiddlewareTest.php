<?php

namespace TNM\Footprints\Tests\Feature;

use Exception;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderBag;
use TNM\Footprints\Http\Middleware\FootprintMiddleware;
use TNM\Footprints\Jobs\FootprintWorker;
use TNM\Footprints\Tests\TestCase;

class FootprintMiddlewareTest extends TestCase
{
    private FootprintMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new FootprintMiddleware();
    }

    public function test_handle_passes_request_to_next_closure(): void
    {
        $request = Request::create('/test', 'GET');
        $expectedResponse = new Response('ok', 200);

        $response = $this->middleware->handle($request, function ($req) use ($expectedResponse) {
            return $expectedResponse;
        });

        $this->assertSame($expectedResponse, $response);
        $this->assertTrue(defined('LARAVEL_START'));
    }

    public function test_terminate_skips_when_footprints_disabled(): void
    {
        config()->set('footprints.enabled', false);
        Queue::fake();

        Log::shouldReceive('debug')
            ->once()
            ->with('Footprint capture is disabled. Skipping...');

        $request = Request::create('/test', 'GET');
        $response = new Response('ok', 200);

        $this->middleware->terminate($request, $response);

        Queue::assertNothingPushed();
    }

    public function test_terminate_dispatches_footprint_worker_when_enabled(): void
    {
        config()->set('footprints.enabled', true);
        config()->set('footprints.queue.connection', 'sync');
        config()->set('footprints.queue.name', 'footprints-queue');
        Queue::fake();

        $request = Request::create('/api/v1/users', 'POST', [
            'username' => 'john_doe',
            'password' => 'secret_pass_123',
        ]);
        $request->headers->set('x-request-id', 'req-abc-999');

        $response = new Response('{"status":"created"}', 201, ['Content-Type' => 'application/json']);

        $this->middleware->terminate($request, $response);

        Queue::assertPushed(FootprintWorker::class, function (FootprintWorker $job) {
            $data = $job->footprint;
            $this->assertSame('req-abc-999', $data['request_id']);
            $this->assertSame('POST', $data['request_method']);
            $this->assertSame('api/v1/users', $data['request_uri']);
            $this->assertEquals(201, $data['response_status_code']);
            $this->assertTrue($data['response_success']);
            $this->assertSame('{"status":"created"}', $data['response_body']);

            // Verify masking
            $this->assertSame('john_doe', $data['request_body']['username']);
            $this->assertSame('[REDACTED]', $data['request_body']['password']);

            return $job->connection === 'sync' && $job->queue === 'footprints-queue';
        });
    }

    public function test_terminate_logs_error_when_dispatch_throws(): void
    {
        config()->set('footprints.enabled', true);

        Log::shouldReceive('error')
            ->once()
            ->withArgs(fn(string $msg) => str_contains($msg, 'Error while dispatching footprint logging job:'));

        Queue::shouldReceive('connection')->andThrow(new Exception('Queue connection failed'));

        $request = Request::create('/test', 'GET');
        $response = new Response('ok', 200);

        $this->middleware->terminate($request, $response);
    }

    public function test_extracts_request_id_from_various_headers(): void
    {
        Queue::fake();

        $headers = [
            'x-request-id' => 'x-req-1',
            'request-id' => 'req-id-2',
            'id' => 'id-3',
            'requestid' => 'reqid-4',
        ];

        foreach ($headers as $header => $value) {
            $request = Request::create('/test', 'GET');
            $request->headers->set($header, $value);
            $response = new Response('ok', 200);

            $this->middleware->terminate($request, $response);

            Queue::assertPushed(FootprintWorker::class, function (FootprintWorker $job) use ($value) {
                return $job->footprint['request_id'] === $value;
            });
        }
    }

    public function test_handles_missing_or_empty_request_id(): void
    {
        Queue::fake();

        $request = Request::create('/test', 'GET');
        $response = new Response('ok', 200);

        $this->middleware->terminate($request, $response);

        Queue::assertPushed(FootprintWorker::class, function (FootprintWorker $job) {
            return $job->footprint['request_id'] === null;
        });

        // Test with empty string header
        $request2 = Request::create('/test', 'GET');
        $request2->headers->set('x-request-id', '');
        $this->middleware->terminate($request2, $response);

        Queue::assertPushed(FootprintWorker::class, function (FootprintWorker $job) {
            return $job->footprint['request_id'] === null;
        });
    }

    public function test_resolves_app_name_and_environment(): void
    {
        Queue::fake();

        // footprints.app_name configured
        config()->set('footprints.app_name', 'my-custom-service');
        config()->set('app.env', 'staging');

        $request = Request::create('/test', 'GET');
        $response = new Response('ok', 200);

        $this->middleware->terminate($request, $response);

        Queue::assertPushed(FootprintWorker::class, function (FootprintWorker $job) {
            return $job->footprint['app_name'] === 'my-custom-service' &&
                   $job->footprint['app_environment'] === 'staging';
        });

        // footprints.app_name fallback to app.name when unset
        $cfg = config('footprints');
        unset($cfg['app_name']);
        config()->set('footprints', $cfg);
        config()->set('app.name', 'laravel-base-app');

        $this->middleware->terminate($request, $response);

        Queue::assertPushed(FootprintWorker::class, function (FootprintWorker $job) {
            return $job->footprint['app_name'] === 'laravel-base-app';
        });

        // fallback to default unnamed-laravel-app when app.name also unset
        $appCfg = config('app');
        unset($appCfg['name']);
        config()->set('app', $appCfg);

        $this->middleware->terminate($request, $response);

        Queue::assertPushed(FootprintWorker::class, function (FootprintWorker $job) {
            return $job->footprint['app_name'] === 'unnamed-laravel-app';
        });
    }

    public function test_captures_authenticated_user(): void
    {
        Queue::fake();

        $user = new GenericUser(['id' => 'user-789', 'name' => 'Alice']);
        $request = Request::create('/profile', 'GET');
        $request->setUserResolver(fn() => $user);

        $response = new Response('profile', 200);

        $this->middleware->terminate($request, $response);

        Queue::assertPushed(FootprintWorker::class, function (FootprintWorker $job) {
            return $job->footprint['user_id'] === 'user-789' &&
                   $job->footprint['user_type'] === GenericUser::class;
        });
    }

    public function test_captures_unauthenticated_user_as_null(): void
    {
        Queue::fake();

        $request = Request::create('/public', 'GET');
        $response = new Response('public', 200);

        $this->middleware->terminate($request, $response);

        Queue::assertPushed(FootprintWorker::class, function (FootprintWorker $job) {
            return $job->footprint['user_id'] === null &&
                   $job->footprint['user_type'] === null;
        });
    }

    public function test_captures_exception_message_from_response(): void
    {
        Queue::fake();

        $request = Request::create('/broken', 'GET');
        $response = new Response('Server Error', 500);
        $response->exception = new Exception('Database timeout error');

        $this->middleware->terminate($request, $response);

        Queue::assertPushed(FootprintWorker::class, function (FootprintWorker $job) {
            return $job->footprint['exception_message'] === 'Database timeout error' &&
                   $job->footprint['response_status_code'] === 500 &&
                   !$job->footprint['response_success'];
        });
    }

    public function test_cleans_uploaded_files_in_request_body(): void
    {
        Queue::fake();

        $file = UploadedFile::fake()->create('document.pdf', 100);
        $avatar = UploadedFile::fake()->image('avatar.jpg');

        $request = Request::create('/upload', 'POST', [
            'user' => [
                'name' => 'Bob',
                'avatar' => $avatar,
            ],
            'doc' => $file,
        ]);

        $response = new Response('uploaded', 200);

        $this->middleware->terminate($request, $response);

        Queue::assertPushed(FootprintWorker::class, function (FootprintWorker $job) {
            $body = $job->footprint['request_body'];
            return $body['doc'] === '[File: document.pdf]' &&
                   $body['user']['avatar'] === '[File: avatar.jpg]' &&
                   $body['user']['name'] === 'Bob';
        });
    }

    public function test_masks_sensitive_headers_and_body_recursively(): void
    {
        Queue::fake();

        config()->set('footprints.mask_fields', 'password,api_key,credit_card,token,cookie,x_api_key');

        $request = Request::create('/sensitive', 'POST', [
            'username' => 'alice',
            'PASSWORD' => 'plain_text_password',
            'api-key' => 'secret_api_key_123',
            'payment' => [
                'credit-card' => '4111-2222-3333-4444',
                'currency' => 'USD',
                'nested' => [
                    'token' => 'nested_token_value',
                    'normal_key' => 'visible_value',
                ],
            ],
        ]);

        $request->headers->set('Cookie', 'session=xyz123');
        $request->headers->set('X-Api-Key', 'header_secret_key');
        $request->headers->set('Accept', 'application/json');

        $response = new Response('done', 200);

        $this->middleware->terminate($request, $response);

        Queue::assertPushed(FootprintWorker::class, function (FootprintWorker $job) {
            $body = $job->footprint['request_body'];
            $headers = $job->footprint['request_headers'];

            $this->assertSame('[REDACTED]', $body['PASSWORD']);
            $this->assertSame('[REDACTED]', $body['api-key']);
            $this->assertSame('[REDACTED]', $body['payment']['credit-card']);
            $this->assertSame('USD', $body['payment']['currency']);
            $this->assertSame('[REDACTED]', $body['payment']['nested']['token']);
            $this->assertSame('visible_value', $body['payment']['nested']['normal_key']);

            $this->assertSame('[REDACTED]', $headers['cookie']);
            $this->assertSame('[REDACTED]', $headers['x-api-key']);
            $this->assertSame(['application/json'], $headers['accept']);

            return true;
        });
    }

    public function test_masks_inputs_accepts_array_mask_config(): void
    {
        Queue::fake();

        config()->set('footprints.mask_fields', ['secret_code', 'pin']);

        $request = Request::create('/code', 'POST', [
            'secret_code' => '9999',
            'PIN' => '1234',
            'public' => 'hello',
        ]);

        $response = new Response('ok', 200);
        $this->middleware->terminate($request, $response);

        Queue::assertPushed(FootprintWorker::class, function (FootprintWorker $job) {
            $body = $job->footprint['request_body'];
            return $body['secret_code'] === '[REDACTED]' &&
                   $body['PIN'] === '[REDACTED]' &&
                   $body['public'] === 'hello';
        });
    }

    public function test_response_content_for_text_and_json(): void
    {
        Queue::fake();

        // JSON
        $request = Request::create('/json', 'GET');
        $response = new Response('{"message":"hello"}', 200, ['Content-Type' => 'application/json']);
        $this->middleware->terminate($request, $response);

        Queue::assertPushed(FootprintWorker::class, function (FootprintWorker $job) {
            return $job->footprint['response_body'] === '{"message":"hello"}';
        });

        // HTML / Text
        $request2 = Request::create('/html', 'GET');
        $response2 = new Response('<h1>Hello</h1>', 200, ['Content-Type' => 'text/html']);
        $this->middleware->terminate($request2, $response2);

        Queue::assertPushed(FootprintWorker::class, function (FootprintWorker $job) {
            return $job->footprint['response_body'] === '<h1>Hello</h1>';
        });

        // XML
        $request3 = Request::create('/xml', 'GET');
        $response3 = new Response('<root><item>1</item></root>', 200, ['Content-Type' => 'application/xml']);
        $this->middleware->terminate($request3, $response3);

        Queue::assertPushed(FootprintWorker::class, function (FootprintWorker $job) {
            return $job->footprint['response_body'] === '<root><item>1</item></root>';
        });
    }

    public function test_response_content_for_binary_and_stream(): void
    {
        Queue::fake();

        $request = Request::create('/download', 'GET');
        $response = new Response('binary data...', 200, ['Content-Type' => 'application/octet-stream']);
        $this->middleware->terminate($request, $response);

        Queue::assertPushed(FootprintWorker::class, function (FootprintWorker $job) {
            return $job->footprint['response_body'] === '(binary/stream content)';
        });
    }

    /**
     * @throws ReflectionException
     */
    public function test_response_content_when_response_is_null(): void
    {
        Queue::fake();

        $ref = new ReflectionClass($this->middleware);
        $method = $ref->getMethod('getResponseContent');
        $method->setAccessible(true);

        $result = $method->invoke($this->middleware, null);
        $this->assertNull($result);
    }
}
