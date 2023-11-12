# Laravel Payments #
A normalized way to manage payments and subscriptions from multiple different payment platforms.

## Installation ##

### Option 1: Add directly to your composer.json ###
```json
"require": {
    "sgtcoder/laravel-payments": "dev-develop"
}

"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/sgtcoder/laravel-payments"
    }
]
```

### Option 2: Fork it and add to your composer.json ###
```json
"require": {
    "sgtcoder/laravel-payments": "dev-master"
}

"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/<workspace>/laravel-payments"
    }
]
```

### Then Run ###
```bash
composer update
```

## Publish Package ##
> Publishes config files and services
```
php artisan vendor:publish --tag laravel-payments-authnet
php artisan vendor:publish --tag laravel-payments-elavon
php artisan vendor:publish --tag laravel-payments-payeezy
php artisan vendor:publish --tag laravel-payments-stripe
```

## Usage ##

## Credits ##
- [sgtcoder](https://github.com/sgtcoder)

## License ##
The MIT License (MIT). Please see [License File](LICENSE.md) for more information.