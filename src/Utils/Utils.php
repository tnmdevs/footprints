<?php

namespace TNM\Footprints\Utils;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

const SASL_PROTOCOLS = [
    'SASL_PLAINTEXT',
    'SASL_SSL',
];

const SASL_MECHANISMS = [
    'PLAIN',
    'GSSAPI',
    'SCRAM-SHA-256',
    'SCRAM-SHA-512',
    'OAUTHBEARER',
];

function getDefaultEventKey(array $footprint): string
{
    $requestMethod = $footprint['request_method'] ?? 'UNKNOWN';

    if (!is_string($requestMethod) || trim($requestMethod) === '') {
        $requestMethod = 'UNKNOWN';
    }

    try {
        $requestTime = isset($footprint['request_time'])
            ? Carbon::parse($footprint['request_time'])
            : now();
    } catch (Throwable) {
        $requestTime = now();
    }

    try {
        $uuid = substr(
            str_replace('-', '', (string)Str::uuid()),
            0,
            8
        );
    } catch (Throwable) {
        $uuid = substr(md5(uniqid('', true)), 0, 8);
    }

    return sprintf(
        'footprint:%s:%s:%s',
        $requestMethod,
        $uuid,
        $requestTime->format('YmdHis')
    );
}


/**
 * Validate Kafka configuration.
 *
 * @throws InvalidArgumentException
 */
function validateConfig(): void
{
    $config = config("footprints.kafka");

    $securityProtocol = $config['security_protocol'] ?? 'PLAINTEXT';
    $saslMechanism = $config['sasl_mechanism'] ?? 'PLAIN';
    $username = $config['sasl_username'] ?? null;
    $password = $config['sasl_password'] ?? null;

    /*
     * security_protocol
     */
    $allowedProtocols = [
        'PLAINTEXT',
        'SSL',
        'SASL_PLAINTEXT',
        'SASL_SSL',
    ];

    if (!in_array($securityProtocol, $allowedProtocols, true)) {
        throw new InvalidArgumentException(
            "Invalid Kafka security_protocol '$securityProtocol'. "
            . 'Allowed values: '
            . implode(', ', $allowedProtocols)
        );
    }

    /*
     * sasl_mechanism
     */
    if (!in_array($saslMechanism, SASL_MECHANISMS, true)) {
        throw new InvalidArgumentException(
            "Invalid Kafka sasl_mechanism '$saslMechanism'. "
            . 'Allowed values: '
            . implode(', ', SASL_MECHANISMS)
        );
    }

    if (
        (null !== $username && '' !== $username) !==
        (null !== $password && '' !== $password)
    ) {
        throw new InvalidArgumentException(
            'Both Kafka username and password must be provided together.'
        );
    }

    $hasCredentials =
        null !== $username &&
        '' !== $username &&
        null !== $password &&
        '' !== $password;

    $isSasl = in_array(
        $securityProtocol,
        SASL_PROTOCOLS,
        true
    );

    if ($hasCredentials && !$isSasl) {
        throw new InvalidArgumentException(
            'Kafka credentials were provided but security_protocol '
            . "is '$securityProtocol'. "
            . 'Credentials require SASL_PLAINTEXT or SASL_SSL.'
        );
    }

    $credentialsRequired = [
        'PLAIN',
        'SCRAM-SHA-256',
        'SCRAM-SHA-512',
    ];

    if (
        $isSasl &&
        in_array($saslMechanism, $credentialsRequired, true) &&
        !$hasCredentials
    ) {
        throw new InvalidArgumentException(
            "Username and password are required for SASL "
            . "'$saslMechanism' mechanism."
        );
    }

    /*
     * GSSAPI/OAUTHBEARER may have authentication requirements
     * that are different from username/password.
     */
}


function checkKafkaHost(
    string $host,
    int    $port
): bool
{
    $name = "<Kafka Broker host=$host port=$port/>";

    try {
        $connection = @fsockopen(
            $host,
            $port,
            $errno,
            $errstr,
            3
        );

        if (is_resource($connection)) {
            fclose($connection);

            Log::debug(
                "[Kafka OK] $name is reachable."
            );

            return true;
        }

        Log::error(
            "[Kafka FAIL] $name is unreachable. "
            . "Error: $errstr ($errno)"
        );

        return false;
    } catch (Exception $e) {
        Log::error(
            "[Kafka FAIL] $name check threw exception: "
            . $e->getMessage()
        );

        return false;
    }
}


function checkBrokers(string $brokerStr): array
{
    $brokers = array_filter(
        array_map('trim', explode(',', $brokerStr)),
        fn(string $broker) => $broker !== ''
    );

    $results = [];

    foreach ($brokers as $broker) {
        [$host, $port] = parseBroker($broker);

        if ($host === null || $port === null) {
            Log::error(
                "[Kafka] Invalid broker configuration: $broker"
            );

            $results[$broker] = false;
            continue;
        }

        $results[$broker] = checkKafkaHost($host, $port);
    }

    return $results;
}

function parseBroker(string $broker): array
{
    $broker = trim($broker);

    if ($broker === '') {
        return [null, null];
    }

    // IPv6: [::1]:9092
    if (
        preg_match(
            '/^\[(.+)]:(\d+)$/',
            $broker,
            $matches
        )
    ) {
        return [
            $matches[1],
            (int)$matches[2],
        ];
    }

    $separator = strrpos($broker, ':');

    if ($separator === false) {
        return [null, null];
    }

    $host = trim(
        substr($broker, 0, $separator)
    );

    $port = trim(
        substr($broker, $separator + 1)
    );

    if (
        $host === '' ||
        !ctype_digit($port)
    ) {
        return [null, null];
    }

    $port = (int)$port;

    if ($port < 1 || $port > 65535) {
        return [null, null];
    }

    return [$host, $port];
}