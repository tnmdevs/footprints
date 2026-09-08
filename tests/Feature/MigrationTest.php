<?php

namespace TNM\Footprints\Tests\Feature;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use TNM\Footprints\Tests\TestCase;

class MigrationTest extends TestCase
{
    public function test_creates_table_with_all_expected_columns(): void
    {
        $tableName = config('footprints.table_name');

        $this->assertTrue(Schema::hasTable($tableName));

        $expectedColumns = [
            'id',
            'request_id',
            'app_name',
            'app_environment',
            'request_method',
            'request_uri',
            'request_url',
            'request_time',
            'request_headers',
            'request_body',
            'response_status_code',
            'response_success',
            'response_headers',
            'response_body',
            'duration_ms',
            'client_ip',
            'host_ip',
            'host_name',
            'user_id',
            'user_type',
            'exception_message',
            'created_at',
            'updated_at',
        ];

        foreach ($expectedColumns as $column) {
            $this->assertTrue(
                Schema::hasColumn($tableName, $column),
                "Column '{$column}' should exist on '{$tableName}' table."
            );
        }
    }

    public function test_down_drops_the_table(): void
    {
        $tableName = config('footprints.table_name');
        $migration = include __DIR__ . '/../../database/migrations/create_v3_footprints_table.php';

        $this->assertTrue(Schema::hasTable($tableName));

        $migration->down();

        $this->assertFalse(Schema::hasTable($tableName));

        // re-run up to leave clean state
        $migration->up();
        $this->assertTrue(Schema::hasTable($tableName));
    }

    public function test_up_skips_when_table_name_is_not_defined(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->with('Footprints table name is not defined, skipping migration...');

        try {
            config()->set('footprints.table_name', null);
            $migration = include __DIR__ . '/../../database/migrations/create_v3_footprints_table.php';
            $migration->up();
        } finally {
            config()->set('footprints.table_name', 'application_footprints');
        }
    }

    public function test_down_skips_when_table_name_is_not_defined(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->with('Footprints table name is not defined, skipping migration...');

        try {
            config()->set('footprints.table_name', null);
            $migration = include __DIR__ . '/../../database/migrations/create_v3_footprints_table.php';
            $migration->down();
        } finally {
            config()->set('footprints.table_name', 'application_footprints');
        }
    }
}
