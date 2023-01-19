<?php

namespace TNM\Footprints\Providers;

use Illuminate\Support\ServiceProvider;

class FootprintServiceProvider extends ServiceProvider
{
    public function register()
    {
        //
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            if (!class_exists('CreateFootprintsTable')) {
                $this->publishes([
                    __DIR__ . '/../../database/migrations/create_footprints_table.php' => database_path('migrations/' . date('Y_m_d_His', time()) . '_create_footprints_table.php'),
                ], 'migrations');
            }
        }
    }
}