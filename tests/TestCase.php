<?php

namespace TNM\Footprints\Tests;

use CreateFootprintsTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use TNM\Footprints\Providers\FootprintServiceProvider;

class TestCase extends \Orchestra\Testbench\TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        // additional setup
    }

    protected function getPackageProviders($app): array
    {
        return [
            FootprintServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        include_once __DIR__ . '/../database/migrations/create_footprints_table.php';

        // run the up() method of that migration class
        (new CreateFootprintsTable())->up();
    }
}