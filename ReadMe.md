# Laravel One Time Password (OTP)

OTP generator and validator for Laravel applications. It stores OTP records in
the database, supports configurable token length, expiry, regeneration limits,
and validation attempt limits.

The package was inspired by `seshac/otp-generator`.

## Requirements

- PHP 8.2 or newer
- Laravel 10, 11, 12, or 13

## Installation

Install the package with Composer:

```bash
composer require trianity/laravel-otp
```

The package automatically loads its migration. Run your migrations after
installation:

```bash
php artisan migrate
```

Publish the configuration and translation files when you want to customize them:

```bash
php artisan vendor:publish --provider="Trianity\Otp\Providers\PackageServiceProvider" --tag="otp"
```

This publishes:

- `config/otp.php`
- `lang/vendor/otp`

## Usage

```php
use Illuminate\Support\Str;
use Trianity\Otp\Facades\Otp;

$identifier = Str::random(12);

$otp = Otp::generate($identifier);

$verify = Otp::validate($identifier, $otp->token);
```

Successful validation returns an object similar to:

```php
(object) [
    'status' => true,
    'message' => 'OTP is valid',
    'code' => 0,
]
```

Get the expiration time for an existing OTP:

```php
$expires = Otp::expiredAt($identifier);
```

The returned object contains an `expired_at` Carbon instance when the OTP exists.

## Configuration

You can configure the package in `config/otp.php`:

```php
return [
    'validity' => env('OTP_VALIDITY_TIME', 30),
    'length' => env('OPT_LENGTH', 6),
    'allowedAttempts' => env('OTP_ALLOWED_ATTEMPTS', 5),
    'onlyDigits' => true,
    'useSameToken' => false,
    'deleteOldOtps' => 31,
    'maximumOtpsAllowed' => env('MAXIMUM_OTPS_ALLOWED', 5),
];
```

## Advanced Usage

Configuration values can also be overridden fluently for a call chain:

```php
use Illuminate\Support\Str;
use Trianity\Otp\Facades\Otp;

$identifier = Str::random(12);

$otp = Otp::setValidity(30)
    ->setLength(4)
    ->setMaximumOtpsAllowed(10)
    ->setOnlyDigits(false)
    ->setUseSameToken(true)
    ->generate($identifier);

$verify = Otp::setAllowedAttempts(10)
    ->validate($identifier, $otp->token);
```

Available fluent setters map to the package settings:

- `setValidity(int $minutes)`
- `setLength(int $length)`
- `setMaximumOtpsAllowed(int $count)`
- `setOnlyDigits(bool $onlyDigits)`
- `setUseSameToken(bool $useSameToken)`
- `setAllowedAttempts(int $count)`

## Testing

```bash
./vendor/bin/pest
./vendor/bin/phpstan analyse
```

## License

The MIT License (MIT). Please see the license file for more information.
