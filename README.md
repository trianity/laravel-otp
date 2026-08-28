# Laravel OTP

OTP generator and validator for Laravel applications. The package stores OTP
records in the database and supports configurable token length, expiry,
regeneration limits, and validation attempt limits.

The package was inspired by `seshac/otp-generator`.

Generated OTP values are returned only from `generate()`. The database stores a
hash of the token, and a successfully validated OTP is immediately marked as
expired so it cannot be reused.

## Requirements

- PHP 8.4 or PHP 8.5
- Laravel 12 or Laravel 13 (Laravel 13 is the primary target)

The package follows the Laravel 13 and PHP 8.4/8.5 package conventions. The
runtime dependency is declared in `composer.json` as PHP `^8.4` and
`illuminate/support` `^12.0|^13.0`.

## Installation

Install the package with Composer:

```bash
composer require trianity/laravel-otp
```

The package automatically loads its migrations. Run your migrations after
installation:

```bash
php artisan migrate
```

Publish the configuration and translation files when you want to customize them:

```bash
php artisan vendor:publish --provider="Trianity\Otp\Providers\PackageServiceProvider" --tag="otp"
```

This publishes the following files:

- `config/otp.php`
- `lang/vendor/otp`

## Basic Usage

```php
use Illuminate\Support\Str;
use Trianity\Otp\Facades\Otp;

$identifier = Str::random(12);

$otp = Otp::generate($identifier);

$verify = Otp::validate($identifier, $otp->token);
```

`generate()` returns an object with `status`, `token`, `message`, and `code`.
The plain-text token is available in the response only; it is never stored in
the database.

Successful validation returns an object similar to:

```php
(object) [
    'status' => true,
    'message' => 'OTP is valid',
    'code' => 0,
]
```

After a successful validation, validating the same OTP again returns a failed
response because the stored record is marked as expired.

Get the expiration time for an existing OTP:

```php
$expires = Otp::expiredAt($identifier);
```

The returned object contains an `expired_at` Carbon instance when the OTP
exists.

If an OTP does not exist, has expired, or has reached its attempt limit,
`validate()` returns `status => false`. A successful validation consumes the
OTP.

## Validation Rule

Use `Trianity\Otp\Rules\OtpRule` when the OTP check belongs in a Laravel
validator or `FormRequest`.

```php
use Trianity\Otp\Rules\OtpRule;

$request->validate([
    'otp' => ['required', 'string', new OtpRule($identifier)],
]);
```

The rule calls `Otp::validate()`. A successful validation consumes the OTP, so
use it only in the final step of the login or verification flow.

## Controller Example

The package does not register application routes. Keep routing, guards, user
lookup, session handling, and responses in your app:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Trianity\Otp\Rules\OtpRule;

class OtpLoginController extends Controller
{
    public function verify(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'otp' => ['required', 'string', new OtpRule($request->string('email')->toString())],
        ]);

        $user = User::where('email', $data['email'])->firstOrFail();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended('/dashboard');
    }
}
```

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

The default configuration allows five validation attempts and five generated
OTPs per identifier during the cleanup period. The `OPT_LENGTH` variable name
is retained for compatibility with the package configuration.

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

Fluent overrides apply to the current terminal call only. After `generate()`,
`validate()`, or `expiredAt()`, the generator resets to `config/otp.php` values.

Available fluent setters map to the package settings:

- `setValidity(int $minutes)`
- `setLength(int $length)`
- `setMaximumOtpsAllowed(int $count)`
- `setOnlyDigits(bool $onlyDigits)`
- `setUseSameToken(bool $useSameToken)`
- `setAllowedAttempts(int $count)`

Because tokens are stored as hashes, `setUseSameToken(true)` keeps the existing
stored token valid for the identifier, but it returns `token => null` on later
calls. If you need to resend the same code, keep the generated token from the
original `generate()` response in your delivery flow.

## Database and cleanup

The package loads its OTP migration automatically. Each identifier has one
stored OTP record; regenerating an OTP updates that record. Expired records and
records older than `deleteOldOtps` minutes are removed when a new OTP is
generated.

The package does not register routes, send messages, or implement a login flow.
Deliver the returned token through the channel used by your application and
keep routing, user lookup, guards, sessions, and responses in your app.

## Testing

```bash
./vendor/bin/pest
./vendor/bin/phpstan analyse
```

## License

The MIT License (MIT). Please see the license file for more information.
