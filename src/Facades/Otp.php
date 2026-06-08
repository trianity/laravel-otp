<?php

declare(strict_types=1);

namespace Trianity\Otp\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static object generate(string $identifier)
 * @method static object validate(string $identifier, string $token)
 * @method static object expiredAt(string $identifier)
 * @method static object setValidity(int $params)
 * @method static object setLength(int $params)
 * @method static object setMaximumOtpsAllowed(int $params)
 * @method static object setOnlyDigits(bool $params)
 * @method static object setUseSameToken(bool $params)
 * @method static object setAllowedAttempts(int $params)
 */
class Otp extends Facade
{
    protected static $cached = false;

    protected static function getFacadeAccessor(): string
    {
        return 'otp';
    }
}
