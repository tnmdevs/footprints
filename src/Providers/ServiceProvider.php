<?php

namespace TNM\Footprints\Providers;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use TNM\Footprints\Http\Middleware\FootprintMiddleware;

class ServiceProvider extends BaseServiceProvider
{
    public function boot(Router $router): void
    {
        $this->publishes([
            __DIR__ . '/../config/footprints.php' => config_path('footprints.php'),
        ], 'footprints-config');

        $this->publishes([
            __DIR__ . '/../../database/migrations/create_v3_footprints_table.php' => database_path('migrations/create_v3_footprints_table.php'),
        ], 'footprints-migrations');

        if ($this->app->runningInConsole()) {
            Event::listen(CommandFinished::class, function (CommandFinished $event) {
                if ($event->command === 'vendor:publish') {
                    $tags = $event->input->getOption('tag') ?: [];
                    if (empty($tags) || in_array('footprints-migrations', $tags)) {
                        $migrationsPath = database_path('migrations');
                        $oldFile = $migrationsPath . '/create_v3_footprints_table.php';

                        if (file_exists($oldFile)) {
                            $timestamp = date('Y_m_d_His');
                            $newFile = $migrationsPath . '/' . $timestamp . '_create_v3_footprints_table.php';
                            rename($oldFile, $newFile);
                        }
                    }
                }
            });
        }


        $migrationsPath = database_path('migrations');
        $hasPublishedMigration = false;

        if (is_dir($migrationsPath)) {
            $files = glob($migrationsPath . '/*_create_v3_footprints_table.php');
            $hasPublishedMigration = !empty($files);
        }

        if (!$hasPublishedMigration) {
            $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        }


        $router->aliasMiddleware('footprints', FootprintMiddleware::class);
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/footprints.php', 'footprints'
        );
    }

}