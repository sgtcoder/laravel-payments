<?php

namespace SgtCoder\LaravelPayments\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \SgtCoder\LaravelPayments\LaravelPayments
 */
class LaravelPayments extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \SgtCoder\LaravelPayments\LaravelPayments::class;
    }
}
