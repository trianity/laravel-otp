<?php

declare(strict_types=1);

namespace Trianity\Otp\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static object generate(string $identifier)
 */
class Otp extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'otp';
    }
}
