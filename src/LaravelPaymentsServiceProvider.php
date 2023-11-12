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
            ->name('laravel-payments');

        // Migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Authnet
        $this->publishes([
            __DIR__ . '/../config/authorizenet.php' => config_path('authorizenet.php'),
            __DIR__ . '/../config/laravel-payments.php' => config_path('laravel-payments.php'),
            __DIR__ . '/Services/AuthNetService.php' => app_path('Services/AuthNetService.php'),
        ], 'authnet');

        // Elavon
        $this->publishes([
            __DIR__ . '/../config/laravel-payments.php' => config_path('laravel-payments.php'),
            __DIR__ . '/../config/elavon.php' => config_path('elavon.php'),
            __DIR__ . '/Services/ElavonService.php' => app_path('Services/ElavonService.php'),
        ], 'elavon');

        // Payeezy
        $this->publishes([
            __DIR__ . '/../config/laravel-payments.php' => config_path('laravel-payments.php'),
            __DIR__ . '/../config/payeezy.php' => config_path('payeezy.php'),
            __DIR__ . '/Services/PayeezyService.php' => app_path('Services/PayeezyService.php'),
        ], 'payeezy');

        // Stripe
        $this->publishes([
            __DIR__ . '/../config/laravel-payments.php' => config_path('laravel-payments.php'),
            __DIR__ . '/../config/stripe.php' => config_path('stripe.php'),
            __DIR__ . '/Services/StripeService.php' => app_path('Services/StripeService.php'),
        ], 'stripe');
    }
}
