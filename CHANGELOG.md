# Changelog

All notable changes to this package are documented in this file.

## [Unreleased] - Laravel 13 / PHP 8.4–8.5 update

### Added

- Added `Trianity\Otp\Rules\OtpRule` for use with Laravel validators and
  `FormRequest` classes.
- Added a unique database constraint for OTP identifiers.
- Added documentation for Laravel 13, PHP 8.4 and PHP 8.5, OTP validation, and
  controller integration.

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

- Supported PHP versions: 8.4 and 8.5.
- Supported Laravel versions: 12 and 13.

