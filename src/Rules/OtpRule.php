<?php

declare(strict_types=1);

namespace Trianity\Otp\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Trianity\Otp\Facades\Otp;

class OtpRule implements ValidationRule
{
    public function __construct(
        private readonly string $identifier,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $result = Otp::validate($this->identifier, strval($value));

        if ($result->status === true) {
            return;
        }

        $fail(strval($result->message));
    }
}
