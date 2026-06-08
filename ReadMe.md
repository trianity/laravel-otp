# Laravel One Time Password (OTP)

OTP generator and validator for Laravel applications. It stores OTP records in
the database, supports configurable token length, expiry, regeneration limits,
and validation attempt limits.

The package was inspired by `seshac/otp-generator`.

Generated OTP values are returned only from `generate()`. The database stores a
hash of the token, and a successfully validated OTP is immediately expired so it
cannot be reused.

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

After a successful validation, validating the same OTP again returns a failed
response because the stored record is marked as expired.

Get the expiration time for an existing OTP:

```php
$expires = Otp::expiredAt($identifier);
```

The returned object contains an `expired_at` Carbon instance when the OTP exists.

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
stored token valid for the identifier, but it cannot return the original plain
token on later calls. If you need to resend the same code, keep the generated
token from the original `generate()` response in your delivery flow.

## Testing

```bash
./vendor/bin/pest
./vendor/bin/phpstan analyse
```

## License

The MIT License (MIT). Please see the license file for more information.
