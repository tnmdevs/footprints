<?php

namespace TNM\Footprints\Tests\Feature;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\NullOutput;
use TNM\Footprints\Http\Middleware\FootprintMiddleware;
use TNM\Footprints\Providers\ServiceProvider;
use TNM\Footprints\Tests\TestCase;

class ServiceProviderTest extends TestCase
{
    public function test_merges_default_configuration(): void
    {
        $config = config('footprints');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('enabled', $config);
        $this->assertArrayHasKey('app_name', $config);
        $this->assertArrayHasKey('queue', $config);
        $this->assertArrayHasKey('mask_fields', $config);
        $this->assertArrayHasKey('table_name', $config);
        $this->assertArrayHasKey('kafka', $config);
    }

    /**
     * @throws BindingResolutionException
     */
    public function test_aliases_footprints_middleware(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $middleware = $router->getMiddleware();

        $this->assertArrayHasKey('footprints', $middleware);
        $this->assertSame(FootprintMiddleware::class, $middleware['footprints']);
    }

    public function test_registers_publishable_groups(): void
    {
        $publishes = BaseServiceProvider::$publishes;

        $this->assertArrayHasKey(ServiceProvider::class, $publishes);

        $groups = BaseServiceProvider::$publishGroups;
        $this->assertArrayHasKey('footprints-config', $groups);
        $this->assertArrayHasKey('footprints-migrations', $groups);
    }

    public function test_command_finished_renames_published_migration_when_tag_matches(): void
    {
        $migrationsDir = database_path('migrations');
        if (!is_dir($migrationsDir)) {
            mkdir($migrationsDir, 0755, true);
        }

        $dummyMigration = $migrationsDir . '/create_v3_footprints_table.php';
        file_put_contents($dummyMigration, '<?php // dummy migration');

        try {
            $definition = new InputDefinition([
                new InputOption('tag', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL),
            ]);

            $input = new ArrayInput(['--tag' => ['footprints-migrations']], $definition);
            $output = new NullOutput();

            Event::dispatch(new CommandFinished('vendor:publish', $input, $output, 0));

            $this->assertFileDoesNotExist($dummyMigration);

            $timestamped = glob($migrationsDir . '/*_create_v3_footprints_table.php');
            $this->assertNotEmpty($timestamped);

            // Cleanup
            foreach ($timestamped as $file) {
                @unlink($file);
            }
        } finally {
            if (file_exists($dummyMigration)) {
                @unlink($dummyMigration);
            }
        }
    }

    public function test_command_finished_renames_published_migration_when_no_tags_provided(): void
    {
        $migrationsDir = database_path('migrations');
        if (!is_dir($migrationsDir)) {
            mkdir($migrationsDir, 0755, true);
        }

        $dummyMigration = $migrationsDir . '/create_v3_footprints_table.php';
        file_put_contents($dummyMigration, '<?php // dummy migration');

        try {
            $definition = new InputDefinition([
                new InputOption('tag', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL),
            ]);

            $input = new ArrayInput([], $definition);
            $output = new NullOutput();

            Event::dispatch(new CommandFinished('vendor:publish', $input, $output, 0));

            $this->assertFileDoesNotExist($dummyMigration);

            $timestamped = glob($migrationsDir . '/*_create_v3_footprints_table.php');
            $this->assertNotEmpty($timestamped);

            // Cleanup
            foreach ($timestamped as $file) {
                @unlink($file);
            }
        } finally {
            if (file_exists($dummyMigration)) {
                @unlink($dummyMigration);
            }
        }
    }

    public function test_command_finished_does_not_rename_for_different_tag(): void
    {
        $migrationsDir = database_path('migrations');
        if (!is_dir($migrationsDir)) {
            mkdir($migrationsDir, 0755, true);
        }

        $dummyMigration = $migrationsDir . '/create_v3_footprints_table.php';
        file_put_contents($dummyMigration, '<?php // dummy migration');

        try {
            $definition = new InputDefinition([
                new InputOption('tag', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL),
            ]);

            $input = new ArrayInput(['--tag' => ['footprints-config']], $definition);
            $output = new NullOutput();

            Event::dispatch(new CommandFinished('vendor:publish', $input, $output, 0));

            $this->assertFileExists($dummyMigration);
        } finally {
            if (file_exists($dummyMigration)) {
                @unlink($dummyMigration);
            }
        }
    }

    public function test_command_finished_does_not_rename_for_different_command(): void
    {
        $migrationsDir = database_path('migrations');
        if (!is_dir($migrationsDir)) {
            mkdir($migrationsDir, 0755, true);
        }

        $dummyMigration = $migrationsDir . '/create_v3_footprints_table.php';
        file_put_contents($dummyMigration, '<?php // dummy migration');

        try {
            $input = new ArrayInput([]);
            $output = new NullOutput();

            Event::dispatch(new CommandFinished('migrate', $input, $output, 0));

            $this->assertFileExists($dummyMigration);
        } finally {
            if (file_exists($dummyMigration)) {
                @unlink($dummyMigration);
            }
        }
    }
}
