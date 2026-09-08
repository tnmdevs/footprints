<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Enable Footprints
    |--------------------------------------------------------------------------
    | Master toggle for footprint capturing. When set to false, the middleware
    | skips data collection and job dispatching entirely.
    */
    'enabled' => env('FOOTPRINTS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Application Identifier
    |--------------------------------------------------------------------------
    | Identifies this service/application in footprint payloads and database records.
    */
    'app_name' => env('FOOTPRINTS_APPLICATION_NAME', env('APP_NAME', 'unnamed-laravel-app')),

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    | The queue connection and queue name to which FootprintWorker jobs are sent. Defaults to the app's queue.
    */
    'queue' => [
        'connection' => env('FOOTPRINTS_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'database')),
        'queue' => env('FOOTPRINTS_QUEUE_NAME', 'default'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Redacted / Masked Fields
    |--------------------------------------------------------------------------
    | Comma-separated list of field names that should be replaced with '[REDACTED]'.
    | Applies recursively to request headers and JSON/form-data request bodies.
    */
    'mask_fields' => env(
        'FOOTPRINTS_HIDDEN_FIELDS',
        'password,password_confirmation,new_pin,pin,credit_card,api_key,token,cookie,set-cookie'
    ),

    /*
    |--------------------------------------------------------------------------
    | Database Table Name
    |--------------------------------------------------------------------------
    | Target database table for fallback logging and migrations.
    */
    'table_name' => env('FOOTPRINTS_TABLE_NAME', 'application_footprints'),

    /*
    |--------------------------------------------------------------------------
    | Kafka Driver Settings
    |--------------------------------------------------------------------------
    */
    'kafka' => [
        // Comma-separated broker list, e.g. "kafka1:9092,kafka2:9092"
        'brokers' => env('FOOTPRINTS_KAFKA_BROKERS'),

        // Target Kafka topic
        'topic' => env('FOOTPRINTS_KAFKA_TOPIC', 'application_footprints'),

        // Client ID sent to the broker
        'client_id' => env('FOOTPRINTS_KAFKA_CLIENT_ID', 'laravel_logger'),

        // Socket timeout in milliseconds
        'timeout_ms' => env('FOOTPRINTS_KAFKA_TIMEOUT_MS', 1000),

        // SASL mechanism: PLAIN, GSSAPI, SCRAM-SHA-256, SCRAM-SHA-512, OAUTHBEARER
        'sasl_mechanism' => env('FOOTPRINTS_KAFKA_SASL_MECHANISM'),

        // Security protocol: PLAINTEXT, SSL, SASL_PLAINTEXT, SASL_SSL
        'security_protocol' => env('FOOTPRINTS_KAFKA_SECURITY_PROTOCOL'),

        // SASL Credentials
        'sasl_username' => env('FOOTPRINTS_KAFKA_SASL_USERNAME'),
        'sasl_password' => env('FOOTPRINTS_KAFKA_SASL_PASSWORD'),

        // Callable or Closure used to generate the message key for Kafka partitioning
        'message_key_func' => env('FOOTPRINTS_KAFKA_MESSAGE_KEY_FUNC', 'TNM\Footprints\Utils\getDefaultEventKey')(...),
    ]
];