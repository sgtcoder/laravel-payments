<?php

namespace SgtCoder\LaravelPayments;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelPaymentsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-payments')
            ->hasConfigFile('laravel-payments');

        // Migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Authnet
        $this->publishes([
            __DIR__ . '/Services/AuthNetService.php' => app_path('Services/AuthNetService.php')
        ], 'authnet');

        // Elavon
        $this->publishes([
            __DIR__ . '/Services/ElavonService.php' => app_path('Services/ElavonService.php')
        ], 'elavon');

        // Payeezy
        $this->publishes([
            __DIR__ . '/Services/PayeezyService.php' => app_path('Services/PayeezyService.php')
        ], 'payeezy');

        // Stripe
        $this->publishes([
            __DIR__ . '/Services/StripeService.php' => app_path('Services/StripeService.php')
        ], 'stripe');
    }
}
