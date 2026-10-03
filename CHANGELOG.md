# Changelog

All notable changes to this package are documented in this file.

## [Unreleased]

## [1.0.0] - 2026-10-03

Initial stable release of `trianity/laravel-otp`.

### Added

- Database-backed OTP generation, validation, and expiration lookup through the
  `Otp` facade.
- Configurable token length, validity, character set, generation limits, and
  validation attempt limits, with fluent per-call overrides.
- Automatic Laravel service provider discovery and migration loading.
- Publishable configuration and English and Hungarian translations.
- Added `Trianity\Otp\Rules\OtpRule` for use with Laravel validators and
  `FormRequest` classes.
- Added a unique database constraint for OTP identifiers.
- Added documentation for Laravel 13, PHP 8.4 and PHP 8.5, OTP validation, and
  controller integration.
- MIT license file and installation instructions for the stable 1.x series.

### Changed

- Updated the runtime requirement to PHP `^8.4`.
- Updated Laravel support to `illuminate/support` `^12.0|^13.0`, with Laravel 13
  as the primary target.
- Updated development tooling for the Laravel 13 / PHP 8.4–8.5 ecosystem.
- OTP tokens are stored as hashes and are invalidated immediately after a
  successful validation.
- OTP generation and validation configuration is reset after each terminal
  operation.

### Compatibility

- PHP requirement: `^8.4`, including PHP 8.4 and 8.5.
- Supported Laravel versions: 12 and 13.
- With `useSameToken` enabled, subsequent generation calls return `token => null`;
  the original plain-text token cannot be recovered from its stored hash.

### Upgrading from development versions

- Run `php artisan migrate` to apply the unique identifier constraint. Existing
  duplicate identifiers must be resolved before applying this migration.
- Existing plain-text OTP records are not converted to hashes by the migrations;
  invalidate those records and generate new OTPs when upgrading from a version
  that stored plain-text tokens.
- Successful validation consumes the OTP, including validation via `OtpRule`.
- Fluent configuration overrides reset after `generate()`, `validate()`, or
  `expiredAt()`.
