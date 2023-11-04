<?php

namespace Jhavenz\AutoMigrations\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Jhavenz\AutoMigrations\AutoMigrationsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Jhavenz\\AutoMigrations\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            AutoMigrationsServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        /*
        $migration = include __DIR__.'/../database/migrations/create_laravel-auto-migrations_table.php.stub';
        $migration->up();
        */
    }
}
