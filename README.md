# Footprints

[![Latest Version](https://img.shields.io/packagist/v/tnmdev/footprints.svg?style=flat-square)](https://packagist.org/packages/tnmdev/footprints)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%5E8.2-blue.svg?style=flat-square)](composer.json)
[![Laravel](https://img.shields.io/badge/laravel-%5E9.0%20%7C%20%5E10.0%20%7C%20%5E11.0%20%7C%20%5E12.0%20%7C%20%5E13.0-orange.svg?style=flat-square)](composer.json)

**Footprints** is an asynchronous HTTP request and response logging package for Laravel applications. It captures comprehensive request/response lifecycle metadata and dispatches background queue jobs to publish footprints to an Apache Kafka topic, with an automated fallback to database persistence if Kafka is unavailable or misconfigured.

---

## Features

- **Non-Blocking / Asynchronous Logging**: Request data capture occurs during the request termination phase and is dispatched to a background queue, ensuring zero latency impact on HTTP responses.
- **Apache Kafka Integration**: Native streaming of request footprints to Kafka topics via `ext-rdkafka`.
- **Automated Database Fallback**: If Kafka brokers are unreachable, credentials fail, or publishing throws an error, the queue worker automatically logs the footprint directly into your database.
- **Sensitive Data Redaction**: Automatically redacts sensitive fields (passwords, PINs, tokens, credit cards, etc.) across request bodies and headers. Supports nested structures and kebab-case/snake_case keys.
- **Binary/Stream Protection**: Detects non-text payloads (file downloads, binary streams) to avoid bloating logs.

---

## Requirements

- **PHP**: `^8.2`
- **Laravel**: `^9.0`, `^10.0`, `^11.0`, `^12.0`, or `^13.0`
- **PHP Extension**: `ext-rdkafka` (required for Kafka event streaming)
- A configured Laravel queue worker (e.g., Redis, Database, RabbitMQ, SQS)

---

## Installation

### 1. Configure `composer.json`:
```json
  {
  "repositories": [
    {
      "type": "vcs",
      "url": "git@github.com:tnmdevs/footprints.git"
    }
  ],
  "require": {
    ....
    "tnmdev/footprints": "dev-<branch-name>"
  },
}
```
and then install via composer:
```bash
composer require tnmdev/footprints
```

### 2. Publish Configuration & Migration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=footprints-config
```

Publish the database migration (used for fallback logging):

```bash
php artisan vendor:publish --tag=footprints-migrations
```

> The publisher automatically generates a timestamped migration file in `database/migrations/`. If you do not publish the migration, the package will register and load the migration from its internal package directory automatically.

### 3. Run Migrations

```bash
php artisan migrate
```

---

## Configuration

The configuration file is published to `config/footprints.php`. The package can be configured via this file or via environment variables.


### Environment Variables (.env) Reference

Add the following environment variables to your `.env` file as needed:

```dotenv
# General Settings
FOOTPRINTS_ENABLED=true
FOOTPRINTS_APPLICATION_NAME=my-service-api
FOOTPRINTS_TABLE_NAME=application_footprints

# Queue Configuration
FOOTPRINTS_QUEUE_CONNECTION=redis
FOOTPRINTS_QUEUE_NAME=logging

# Sensitive Data Redaction (comma-separated)
FOOTPRINTS_HIDDEN_FIELDS=cookie,password,password_confirmation,new_pin,pin,credit_card,api_key,token,authorization

# Kafka Broker Configuration (brokers are comma-separated)
FOOTPRINTS_KAFKA_BROKERS=127.0.0.1:9092,127.0.0.1:9093
FOOTPRINTS_KAFKA_TOPIC=application_footprints
FOOTPRINTS_KAFKA_CLIENT_ID=my_app_footprints
FOOTPRINTS_KAFKA_TIMEOUT_MS=1000

# Kafka SASL Authentication (Optional)
FOOTPRINTS_KAFKA_SECURITY_PROTOCOL=SASL_PLAINTEXT
FOOTPRINTS_KAFKA_SASL_MECHANISM=PLAIN
FOOTPRINTS_KAFKA_SASL_USERNAME=your_username
FOOTPRINTS_KAFKA_SASL_PASSWORD=your_password
```

---

## Usage & Middleware Registration

To start capturing request and response footprints, apply the middleware to your routes or middleware pipeline. The package automatically registers the middleware alias `footprints`.

### Laravel 11 and Newer (`bootstrap/app.php`)

#### Global / Group Registration:

```php
use TNM\Footprints\Http\Middleware\FootprintMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Append globally to all requests
        $middleware->append(FootprintMiddleware::class);

        // Or append to API group only
        $middleware->api(append: [
            FootprintMiddleware::class,
        ]);
    })
    // ...
```

### Laravel 9 & 10 (`app/Http/Kernel.php`)

#### Global or Route Group:

```php
protected $middlewareGroups = [
    'api' => [
        // ...
        \TNM\Footprints\Http\Middleware\FootprintMiddleware::class,
    ],
];
```

### Route-Specific Usage

You can use the alias `'footprints'` directly on route groups or single routes:

```php
use Illuminate\Support\Facades\Route;

Route::middleware(['footprints'])->group(function () {
    Route::post('/api/checkout', [CheckoutController::class, 'process']);
    Route::resource('/api/orders', OrderController::class);
});
```

---

## How It Works

```
                        HTTP Request
                             │
                             ▼
               ┌───────────────────────────┐
               │    FootprintMiddleware    │
               │   (handle: pass request)  │
               └─────────────┬─────────────┘
                             │
                             ▼
                    Application Logic
                             │
                             ▼
               ┌───────────────────────────┐
               │    FootprintMiddleware    │
               │   (terminate: collect)    │
               └─────────────┬─────────────┘
                             │
                  Dispatches FootprintWorker
                             │
                             ▼
                    [ Laravel Queue ]
                             │
                             ▼
               ┌───────────────────────────┐
               │      FootprintWorker      │
               └─────────────┬─────────────┘
                             │
             Attempts Kafka Produce
                             │
             ┌───────────────┴───────────────┐
             │                               │
        [ Success ]                     [ Failure ]
             │                               │
             ▼                               ▼
       Apache Kafka                  Direct DB Insert
          Topic                  (`application_footprints`)
```
> If Kafka is unreachable, misconfigured, or the RdKafka extension is not loaded, the job catches the error and writes the footprint directly into the database.

---

## Kafka Configuration & Keys

### Supported Security Protocols & SASL Mechanisms

- **Security Protocols**: `PLAINTEXT`, `SSL`, `SASL_PLAINTEXT`, `SASL_SSL`
- **SASL Mechanisms**: `PLAIN`, `GSSAPI`, `SCRAM-SHA-256`, `SCRAM-SHA-512`, `OAUTHBEARER`

Broker addresses can be IPv4 or IPv6, including ports (e.g., `127.0.0.1:9092`, `kafka.service.internal:9093`, `[::1]:9092`).

### Custom Partition Message Key

By default, message keys sent to Kafka are generated using `TNM\Footprints\Utils\getDefaultEventKey()` in the format:

```
footprint:{METHOD}:{UUID_PREFIX_8}:{YmdHis}
# Example: footprint:POST:a3f1c99b:20260906124500
```

To define a custom key (e.g., partitioning by `request_id` or `user_id`), define a callback or closure in `config/footprints.php`:

```php
'kafka' => [
    // ...
    'message_key_func' => function (array $footprint) {
        return $footprint['request_id'] ?? $footprint['user_id'] ?? 'default_key';
    },
],
```

---

## Data Structure

Every footprint contains the following fields:

| Field | Type | Description |
|---|---|---|
| `request_id` | `string` | Extracted from `x-request-id`, `request-id`, `id`, or `requestid` headers. |
| `app_name` | `string` | Name of the application/microservice (from config `app_name`). |
| `app_environment` | `string` | Environment name (e.g. `production`, `staging`, `local`). |
| `request_method` | `string` | HTTP Verb (e.g. `GET`, `POST`, `PUT`, `DELETE`). |
| `request_uri` | `string` | The URI path (e.g. `api/v1/orders`). |
| `request_url` | `string` | The full URL including scheme, host, and query string. |
| `request_time` | `string` | ISO 8601 UTC timestamp of the request start. |
| `request_headers` | `array` / `json` | Associative array of request headers with sensitive headers masked. |
| `request_body` | `array` / `json` | Cleaned and masked request input parameters. Uploaded files appear as `[File: filename]`. |
| `response_status_code` | `int` | HTTP response status code (e.g. `200`, `404`, `500`). |
| `response_success` | `bool` | Boolean status indicating whether the response status code is 2xx. |
| `response_headers` | `array` / `json` | Headers returned with the HTTP response. |
| `response_body` | `string` | Response payload content (binary/stream responses are replaced with `(binary/stream content)`). |
| `duration_ms` | `float` | Request round-trip execution duration in milliseconds. |
| `client_ip` | `string` | Client IP address. |
| `host_ip` | `string` | Server host IP address. |
| `host_name` | `string` | Server hostname. |
| `user_id` | `string` / `null` | Primary identifier of the authenticated user. |
| `user_type` | `string` / `null` | Fully-qualified class name of the authenticated user model. |
| `exception_message` | `string` / `null` | Exception message if an error occurred during request processing or fallback reason. |

---

## Queue Workers

Because footprint logging jobs are queued asynchronously, ensure that your queue workers are running:

```bash
# Process jobs on the configured connection and queue
php artisan queue:work redis --queue=logging
```

For production, manage your queue workers using a process supervisor like [Supervisor](http://supervisord.org/) or [Laravel Horizon](https://laravel.com/docs/horizon) (for Redis).

---

## Contributing

Pull requests and issues are welcome! Please ensure all code conforms to PSR-12 and tests pass before submitting changes.

---

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).
