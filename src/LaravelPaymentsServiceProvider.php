<?php

namespace SgtCoder\LaravelPayments;

use Illuminate\Support\ServiceProvider;

// https://laravel.com/docs/10.x/packages
class LaravelPaymentsServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/laravel-payments.php' => config_path('laravel-payments.php'),
            ]);

            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
    }
}
