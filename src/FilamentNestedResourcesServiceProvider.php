<?php

namespace Darmshot\FilamentNestedResources;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentNestedResourcesServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-nested-resources';
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package->name(static::$name);
    }
}
