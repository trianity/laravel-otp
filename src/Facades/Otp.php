<?php

namespace Trianity\Otp\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static object generate(string $identifier)
 */
class Otp extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'otp';
    }
}
