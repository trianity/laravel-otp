# Laravel OTP

[Source code](https://github.com/trianity/laravel-otp) · [Changelog](CHANGELOG.md) · [2.0 upgrade guide](UPGRADE-2.0.md) · [MIT License](LICENSE)

OTP generator and validator for Laravel applications. The package stores OTP
records in the database and supports configurable token length, expiry,
regeneration limits, and validation attempt limits.

The package was inspired by `seshac/otp-generator`.

Generated OTP values are returned only from `generate()`. The database stores a
hash of the token, and a successfully validated OTP is immediately marked as
expired so it cannot be reused.

## Requirements for 2.x

- PHP `^8.4` (including PHP 8.4 and 8.5)
- Laravel 12 or Laravel 13 (`illuminate/support` `^12.0|^13.0`)

Laravel 13 is the primary target. A database connection configured in your
Laravel application is required to store OTP records.

## Installation

Install the stable 2.x series with Composer:

```bash
composer require trianity/laravel-otp:^2.0
```

Laravel automatically discovers the service provider and the `Otp` facade;
manual registration is not required.

The package automatically loads its migrations. Run your migrations after
installation:

```bash
php artisan migrate
```

Applications upgrading from 1.0.0 must also run this command so the corrective
`generated_at` migration is applied. Do not overwrite the old migration or use
`migrate:fresh`; follow [UPGRADE-2.0.md](UPGRADE-2.0.md).

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

if ($otp->status && $otp->token !== null) {
    // Deliver this token through your application, then validate the submitted code.
    $verify = Otp::validate($identifier, $otp->token);
}
```

On success, `generate()` returns an object with `status`, `token`, `message`, and `code`.
On failure, it returns `status => false`, `message`, and `code`; check `status`
before accessing `token`.
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

## Custom OTP clock

Version 2.1 adds the optional `Trianity\Otp\Contracts\OtpClock` extension point:

```php
public function now(): \DateTimeImmutable;
```

By default, `LaravelOtpClock` reads Laravel's Carbon clock on every call. This
preserves the existing application timezone and Carbon test-time behaviour; it
does not capture a time in its constructor or change global clock state.

An application that needs OTPs to use the real system clock while its global
Carbon clock is frozen can provide a native implementation:

```php
<?php

namespace App\Support;

use DateTimeImmutable;
use DateTimeZone;
use Trianity\Otp\Contracts\OtpClock;

final class SystemOtpClock implements OtpClock
{
    private readonly DateTimeZone $timezone;

    public function __construct()
    {
        $this->timezone = new DateTimeZone('UTC');
    }

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', $this->timezone);
    }
}
```

Bind it from an application service provider:

```php
use App\Support\SystemOtpClock;
use Trianity\Otp\Contracts\OtpClock;

public function register(): void
{
    $this->app->singleton(OtpClock::class, SystemOtpClock::class);
}
```

The same binding must be loaded in HTTP, queue-worker, and console processes so
all OTP lifecycle decisions use one time source. A long-running clock service
must calculate the current time inside `now()` rather than storing its
construction time.

Automated tests can bind a mutable fake without changing Carbon globally:

```php
$fakeClock = new class(new \DateTimeImmutable('2026-10-05 21:21:35 UTC')) implements OtpClock
{
    public function __construct(private \DateTimeImmutable $currentTime) {}

    public function now(): \DateTimeImmutable
    {
        return $this->currentTime;
    }

    public function advance(\DateInterval $interval): void
    {
        $this->currentTime = $this->currentTime->add($interval);
    }
};

$this->app->instance(OtpClock::class, $fakeClock);
$fakeClock->advance(new \DateInterval('PT26S'));
```

Restore the binding after the test, for example with
`$this->app->forgetInstance(OtpClock::class)`. Changing clocks does not migrate,
rewrite, or delete existing OTP records. Plan how active codes will be
invalidated or allowed to expire before switching between time sources.

## Database and cleanup

The package loads its OTP migration automatically. Each identifier has one
stored OTP record; regenerating an OTP updates that record. Expired records and
records older than `deleteOldOtps` minutes are removed when a new OTP is
generated.

`generated_at` is application-managed. It changes when `generate()` creates or
regenerates an OTP, including a `useSameToken` resend, but not when counters or
other columns change. Generation, regeneration, expiry checks, and cleanup use
the bound `OtpClock`. With the default binding, Laravel's Carbon test clock
continues to control all these operations. An OTP is valid through its exact
expiry instant and is expired immediately after it.

The package does not register routes, send messages, or implement a login flow.
Deliver the returned token through the channel used by your application and
keep routing, user lookup, guards, sessions, and responses in your app.

## Testing

From a source checkout, install development dependencies and run the checks:

```bash
composer install
./vendor/bin/pest
./vendor/bin/phpstan analyse
./vendor/bin/pint --test
composer validate --strict
```

The MariaDB integration suite is opt-in and requires an isolated database:

```bash
OTP_MARIADB_HOST=127.0.0.1 \
OTP_MARIADB_PORT=3306 \
OTP_MARIADB_DATABASE=laravel_otp_test \
OTP_MARIADB_USERNAME=root \
OTP_MARIADB_PASSWORD=secret \
./vendor/bin/pest tests/Integration
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for release notes and [UPGRADE-2.0.md](UPGRADE-2.0.md)
for the 1.0.0 to 2.0.0 database upgrade.

## License

This package is licensed under the [MIT License](LICENSE).
