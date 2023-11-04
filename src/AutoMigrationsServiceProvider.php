<?php

namespace Jhavenz\AutoMigrations;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Jhavenz\AutoMigrations\Commands\AutoMigrationsCommand;

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
                AutoMigrationsCommand::class
            ]);
    }
}
