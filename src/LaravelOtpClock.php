<?php

declare(strict_types=1);

namespace Trianity\Otp;

use DateTimeImmutable;
use Illuminate\Support\Carbon;
use Trianity\Otp\Contracts\OtpClock;

final class LaravelOtpClock implements OtpClock
{
    public function now(): DateTimeImmutable
    {
        return Carbon::now()->toImmutable();
    }
}
