<?php

namespace TNM\Footprints\Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use TNM\Footprints\Tests\TestCase;

class IntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('footprints.enabled', true);
        config()->set('footprints.kafka.brokers', ''); // Force immediate DB fallback without socket delay
        config()->set('queue.default', 'sync');
        config()->set('footprints.queue.connection', 'sync');
        config()->set('footprints.queue.name', 'default');
    }

    public function test_end_to_end_successful_request_with_database_fallback(): void
    {
        Route::post('/test-checkout', function (Request $request) {
            return response()->json([
                'order_id' => 101,
                'status' => 'placed',
            ], 200);
        })->middleware('footprints');

        $response = $this->post('/test-checkout', [
            'item' => 'laptop',
            'password' => 'secret_password_123',
            'price' => 1200,
        ], [
            'x-request-id' => 'ord-req-777',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['order_id' => 101, 'status' => 'placed']);

        $record = DB::table('application_footprints')->where('request_id', 'ord-req-777')->first();

        $this->assertNotNull($record);
        $this->assertSame('ord-req-777', $record->request_id);
        $this->assertSame('POST', $record->request_method);
        $this->assertSame('test-checkout', $record->request_uri);
        $this->assertEquals(200, $record->response_status_code);
        $this->assertTrue((bool)$record->response_success);

        $body = json_decode($record->request_body, true);
        $this->assertSame('laptop', $body['item']);
        $this->assertSame('[REDACTED]', $body['password']);
        $this->assertEquals(1200, $body['price']);

        $this->assertStringContainsString('101', $record->response_body);
        $this->assertStringContainsString('[Kafka] No brokers configured', $record->exception_message);
    }

    public function test_end_to_end_request_with_authenticated_user(): void
    {
        Route::get('/user-profile', function (Request $request) {
            return response()->json([
                'user' => $request->user()->getAuthIdentifier(),
            ]);
        })->middleware('footprints');

        $user = new GenericUser(['id' => 'auth-user-99', 'name' => 'Alice']);

        $response = $this->actingAs($user)->get('/user-profile', [
            'request-id' => 'auth-req-123',
        ]);

        $response->assertStatus(200);

        $record = DB::table('application_footprints')->where('request_id', 'auth-req-123')->first();

        $this->assertNotNull($record);
        $this->assertSame('auth-user-99', $record->user_id);
        $this->assertSame(GenericUser::class, $record->user_type);
    }

    public function test_end_to_end_request_with_unhandled_exception(): void
    {
        Route::get('/error-route', function () {
            throw new RuntimeException('Database server crashed');
        })->middleware('footprints');

        $response = $this->get('/error-route', [
            'x-request-id' => 'err-req-500',
        ]);

        $response->assertStatus(500);

        $record = DB::table('application_footprints')->where('request_id', 'err-req-500')->first();

        $this->assertNotNull($record);
        $this->assertEquals(500, $record->response_status_code);
        $this->assertFalse((bool)$record->response_success);
        $this->assertSame('Database server crashed', $record->exception_message);
    }

    public function test_end_to_end_request_with_file_upload(): void
    {
        Route::post('/upload-receipt', function (Request $request) {
            return response()->json(['uploaded' => true]);
        })->middleware('footprints');

        $file = UploadedFile::fake()->create('receipt.pdf', 50);

        $response = $this->post('/upload-receipt', [
            'receipt' => $file,
            'description' => 'Business trip receipt',
        ], [
            'x-request-id' => 'file-req-456',
        ]);

        $response->assertStatus(200);

        $record = DB::table('application_footprints')->where('request_id', 'file-req-456')->first();

        $this->assertNotNull($record);
        $body = json_decode($record->request_body, true);
        $this->assertSame('[File: receipt.pdf]', $body['receipt']);
        $this->assertSame('Business trip receipt', $body['description']);
    }

    public function test_end_to_end_request_with_binary_stream_response(): void
    {
        Route::get('/stream-download', function () {
            return response('binary-bytes-data', 200, [
                'Content-Type' => 'application/octet-stream',
            ]);
        })->middleware('footprints');

        $response = $this->get('/stream-download', [
            'x-request-id' => 'stream-req-888',
        ]);

        $response->assertStatus(200);

        $record = DB::table('application_footprints')->where('request_id', 'stream-req-888')->first();

        $this->assertNotNull($record);
        $this->assertSame('(binary/stream content)', $record->response_body);
    }

    public function test_end_to_end_request_when_footprints_disabled(): void
    {
        Route::get('/disabled-test', function () {
            return response('ok', 200);
        })->middleware('footprints');

        config()->set('footprints.enabled', false);

        $response = $this->get('/disabled-test', [
            'x-request-id' => 'disabled-req-000',
        ]);

        $response->assertStatus(200);

        $record = DB::table('application_footprints')->where('request_id', 'disabled-req-000')->first();

        $this->assertNull($record);
    }
}
