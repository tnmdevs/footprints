<?php

namespace TNM\Footprints\Tests\Unit\Utils;

use InvalidArgumentException;
use TNM\Footprints\Tests\TestCase;
use function TNM\Footprints\Utils\validateConfig;

class ValidateConfigTest extends TestCase
{
    public function test_passes_with_default_plaintext_config(): void
    {
        config()->set('footprints.kafka', [
            'security_protocol' => 'PLAINTEXT',
            'sasl_mechanism' => 'PLAIN',
        ]);

        validateConfig();
        $this->assertTrue(true);
    }

    public function test_passes_with_ssl_protocol_without_credentials(): void
    {
        config()->set('footprints.kafka', [
            'security_protocol' => 'SSL',
            'sasl_mechanism' => 'PLAIN',
        ]);

        validateConfig();
        $this->assertTrue(true);
    }

    public function test_passes_with_valid_sasl_plaintext_and_credentials(): void
    {
        config()->set('footprints.kafka', [
            'security_protocol' => 'SASL_PLAINTEXT',
            'sasl_mechanism' => 'PLAIN',
            'sasl_username' => 'alice',
            'sasl_password' => 'secret123',
        ]);

        validateConfig();
        $this->assertTrue(true);
    }

    public function test_passes_with_valid_sasl_ssl_and_scram_256(): void
    {
        config()->set('footprints.kafka', [
            'security_protocol' => 'SASL_SSL',
            'sasl_mechanism' => 'SCRAM-SHA-256',
            'sasl_username' => 'alice',
            'sasl_password' => 'secret123',
        ]);

        validateConfig();
        $this->assertTrue(true);
    }

    public function test_passes_with_valid_sasl_ssl_and_scram_512(): void
    {
        config()->set('footprints.kafka', [
            'security_protocol' => 'SASL_SSL',
            'sasl_mechanism' => 'SCRAM-SHA-512',
            'sasl_username' => 'alice',
            'sasl_password' => 'secret123',
        ]);

        validateConfig();
        $this->assertTrue(true);
    }

    public function test_passes_with_sasl_gssapi_without_credentials(): void
    {
        config()->set('footprints.kafka', [
            'security_protocol' => 'SASL_PLAINTEXT',
            'sasl_mechanism' => 'GSSAPI',
        ]);

        validateConfig();
        $this->assertTrue(true);
    }

    public function test_passes_with_sasl_oauthbearer_without_credentials(): void
    {
        config()->set('footprints.kafka', [
            'security_protocol' => 'SASL_SSL',
            'sasl_mechanism' => 'OAUTHBEARER',
        ]);

        validateConfig();
        $this->assertTrue(true);
    }

    public function test_throws_exception_on_invalid_security_protocol(): void
    {
        config()->set('footprints.kafka', [
            'security_protocol' => 'INVALID_PROTOCOL',
            'sasl_mechanism' => 'PLAIN',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid Kafka security_protocol 'INVALID_PROTOCOL'. Allowed values: PLAINTEXT, SSL, SASL_PLAINTEXT, SASL_SSL");

        validateConfig();
    }

    public function test_throws_exception_on_invalid_sasl_mechanism(): void
    {
        config()->set('footprints.kafka', [
            'security_protocol' => 'PLAINTEXT',
            'sasl_mechanism' => 'INVALID_MECHANISM',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid Kafka sasl_mechanism 'INVALID_MECHANISM'. Allowed values: PLAIN, GSSAPI, SCRAM-SHA-256, SCRAM-SHA-512, OAUTHBEARER");

        validateConfig();
    }

    public function test_throws_exception_when_username_provided_without_password(): void
    {
        config()->set('footprints.kafka', [
            'security_protocol' => 'SASL_PLAINTEXT',
            'sasl_mechanism' => 'PLAIN',
            'sasl_username' => 'alice',
            'sasl_password' => null,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Both Kafka username and password must be provided together.');

        validateConfig();
    }

    public function test_throws_exception_when_username_provided_with_empty_string_password(): void
    {
        config()->set('footprints.kafka', [
            'security_protocol' => 'SASL_PLAINTEXT',
            'sasl_mechanism' => 'PLAIN',
            'sasl_username' => 'alice',
            'sasl_password' => '',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Both Kafka username and password must be provided together.');

        validateConfig();
    }

    public function test_throws_exception_when_password_provided_without_username(): void
    {
        config()->set('footprints.kafka', [
            'security_protocol' => 'SASL_PLAINTEXT',
            'sasl_mechanism' => 'PLAIN',
            'sasl_username' => null,
            'sasl_password' => 'secret123',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Both Kafka username and password must be provided together.');

        validateConfig();
    }

    public function test_throws_exception_when_password_provided_with_empty_string_username(): void
    {
        config()->set('footprints.kafka', [
            'security_protocol' => 'SASL_PLAINTEXT',
            'sasl_mechanism' => 'PLAIN',
            'sasl_username' => '',
            'sasl_password' => 'secret123',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Both Kafka username and password must be provided together.');

        validateConfig();
    }

    public function test_throws_exception_when_credentials_provided_with_plaintext_protocol(): void
    {
        config()->set('footprints.kafka', [
            'security_protocol' => 'PLAINTEXT',
            'sasl_mechanism' => 'PLAIN',
            'sasl_username' => 'alice',
            'sasl_password' => 'secret123',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Kafka credentials were provided but security_protocol is 'PLAINTEXT'. Credentials require SASL_PLAINTEXT or SASL_SSL.");

        validateConfig();
    }

    public function test_throws_exception_when_credentials_provided_with_ssl_protocol(): void
    {
        config()->set('footprints.kafka', [
            'security_protocol' => 'SSL',
            'sasl_mechanism' => 'PLAIN',
            'sasl_username' => 'alice',
            'sasl_password' => 'secret123',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Kafka credentials were provided but security_protocol is 'SSL'. Credentials require SASL_PLAINTEXT or SASL_SSL.");

        validateConfig();
    }

    public function test_throws_exception_when_sasl_plain_is_missing_credentials(): void
    {
        config()->set('footprints.kafka', [
            'security_protocol' => 'SASL_PLAINTEXT',
            'sasl_mechanism' => 'PLAIN',
            'sasl_username' => null,
            'sasl_password' => null,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Username and password are required for SASL 'PLAIN' mechanism.");

        validateConfig();
    }

    public function test_throws_exception_when_sasl_scram_256_is_missing_credentials(): void
    {
        config()->set('footprints.kafka', [
            'security_protocol' => 'SASL_SSL',
            'sasl_mechanism' => 'SCRAM-SHA-256',
            'sasl_username' => null,
            'sasl_password' => null,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Username and password are required for SASL 'SCRAM-SHA-256' mechanism.");

        validateConfig();
    }

    public function test_throws_exception_when_sasl_scram_512_is_missing_credentials(): void
    {
        config()->set('footprints.kafka', [
            'security_protocol' => 'SASL_SSL',
            'sasl_mechanism' => 'SCRAM-SHA-512',
            'sasl_username' => null,
            'sasl_password' => null,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Username and password are required for SASL 'SCRAM-SHA-512' mechanism.");

        validateConfig();
    }
}
