<?php

declare(strict_types=1);

namespace Trianity\Otp\Contracts;

use DateTimeImmutable;

interface OtpClock
{
    public function now(): DateTimeImmutable;
}
