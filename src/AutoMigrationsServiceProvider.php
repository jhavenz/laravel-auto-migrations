<?php

namespace Jhavenz\AutoMigrations;

use Jhavenz\AutoMigrations\Commands\AutoMigrationsCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class AutoMigrationsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-auto-migrations')
            ->hasConfigFile()
            ->hasCommands([
                AutoMigrationsCommand::class,
            ]);
    }
}
