<?php

namespace TNM\Footprints\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use TNM\Footprints\Providers\ServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('footprints.enabled', true);
        $app['config']->set('footprints.table_name', 'application_footprints');
        $app['config']->set('footprints.queue.connection', 'sync');
        $app['config']->set('footprints.queue.name', 'default');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
