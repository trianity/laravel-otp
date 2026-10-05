<?php

declare(strict_types=1);

namespace Trianity\Otp;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Trianity\Otp\Contracts\OtpClock;
use Trianity\Otp\Models\Otp as OtpModel;

class OtpGenerator
{
    /**
     * Length of the generated OTP
     */
    protected int $length;

    /**
     * Generated OPT type
     */
    protected bool $onlyDigits;

    /**
     * use same token to resending opt
     */
    protected bool $useSameToken;

    /**
     * Otp Validity time
     */
    protected int $validity;

    /**
     * Delete old otps
     */
    protected int $deleteOldOtps;

    /**
     * Maximum otps allowed to generate
     */
    protected int $maximumOtpsAllowed;

    /**
     * Maximum number of times to allowed to validate
     */
    protected int $allowedAttempts;

    protected OtpClock $clock;

    public function __construct(?OtpClock $clock = null)
    {
        $this->clock = $clock ?? new LaravelOtpClock;
        $this->resetConfig();
    }

    /**
     * When a method is called, look for the 'set' prefix and attempt to set the
     * matching property to the value passed to the method and return a chainable
     * object to the caller.
     *
     * @param  array<int, mixed>  $params
     */
    public function __call(string $method, array $params): ?object
    {
        if (! Str::of(substr($method, 0, 3))->exactly('set')) {
            return null;
        }

        $property = Str::camel(substr($method, 3));

        // Does the property exist on this object?
        if (! property_exists($this, $property)) {
            return null;
        }

        $this->{$property} = $params[0] ?? null;

        return $this;
    }

    public function generate(string $identifier): object
    {
        try {
            $now = $this->clock->now();
            $this->deleteOldOtps($now);

            $otp = OtpModel::where('identifier', $identifier)->first();

            if (! $otp instanceof OtpModel) {
                $token = $this->createPin();

                try {
                    $otp = OtpModel::create([
                        'identifier' => $identifier,
                        'token' => Hash::make($token),
                        'validity' => $this->validity,
                        'no_times_generated' => 1,
                        'generated_at' => $now,
                    ]);
                } catch (QueryException $exception) {
                    $otp = OtpModel::where('identifier', $identifier)->first();

                    if ($otp instanceof OtpModel) {
                        return $this->updateOtp($otp, $identifier, $now);
                    }

                    throw $exception;
                }

                return (object) [
                    'status' => true,
                    'token' => $token,
                    'message' => trans('otp::messages.otp_generated'),
                    'code' => 0,
                ];
            }

            return $this->updateOtp($otp, $identifier, $now);
        } finally {
            $this->resetConfig();
        }
    }

    public function validate(string $identifier, string $token): object
    {
        try {
            $otp = OtpModel::where('identifier', $identifier)->first();

            if (! $otp instanceof OtpModel) {
                return (object) [
                    'status' => false,
                    'message' => trans('otp::messages.otp_missing'),
                    'code' => 1,
                ];
            }

            if ($otp->isExpired($this->clock->now())) {
                return (object) [
                    'status' => false,
                    'message' => trans('otp::messages.otp_expired'),
                    'code' => 1,
                ];
            }

            if ($otp->no_times_attempted >= $this->allowedAttempts) {
                return (object) [
                    'status' => false,
                    'message' => trans('otp::messages.otp_max_reached'),
                    'code' => 3,
                ];
            }

            $otp->incrementAttempts();

            if (! Hash::check($token, $otp->token)) {
                return (object) [
                    'status' => false,
                    'message' => trans('otp::messages.otp_wrong'),
                    'code' => 2,
                ];
            }

            $otp->markExpired();

            return (object) [
                'status' => true,
                'message' => trans('otp::messages.otp_valid'),
                'code' => 0,
            ];
        } finally {
            $this->resetConfig();
        }
    }

    public function expiredAt(string $identifier): object
    {
        try {
            $otp = OtpModel::where('identifier', $identifier)->first();

            if (! $otp) {
                return (object) [
                    'status' => false,
                    'message' => trans('otp::messages.otp_missing'),
                    'code' => 1,
                ];
            }

            return (object) [
                'status' => true,
                'expired_at' => $otp->expiredAt(),
                'code' => 0,
            ];
        } finally {
            $this->resetConfig();
        }
    }

    protected function updateOtp(OtpModel $otp, string $identifier, DateTimeImmutable $now): object
    {
        if ($otp->no_times_generated === $this->maximumOtpsAllowed) {
            return (object) [
                'status' => false,
                'message' => trans('otp::messages.otp_max_gen'),
                'code' => 3,
            ];
        }

        $token = $this->useSameToken ? null : $this->createPin();

        $otp->increment('no_times_generated', 1, [
            'identifier' => $identifier,
            'token' => $token === null ? $otp->token : Hash::make($token),
            'validity' => $this->validity,
            'generated_at' => $now,
        ]);

        return (object) [
            'status' => true,
            'token' => $token,
            'message' => trans('otp::messages.otp_generated'),
            'code' => 0,
        ];
    }

    private function deleteOldOtps(DateTimeImmutable $now): void
    {
        $deleteBefore = CarbonImmutable::instance($now)->subMinutes($this->deleteOldOtps);

        OtpModel::where('expired', true)
            ->orWhere('generated_at', '<', $deleteBefore)
            ->delete();
    }

    private function resetConfig(): void
    {
        $this->length = (int) config('otp.length');
        $this->onlyDigits = filter_var(config('otp.onlyDigits'), FILTER_VALIDATE_BOOL);
        $this->useSameToken = filter_var(config('otp.useSameToken'), FILTER_VALIDATE_BOOL);
        $this->validity = (int) config('otp.validity');
        $this->deleteOldOtps = (int) config('otp.deleteOldOtps');
        $this->maximumOtpsAllowed = (int) config('otp.maximumOtpsAllowed');
        $this->allowedAttempts = (int) config('otp.allowedAttempts');
    }

    private function createPin(): string
    {
        if ($this->onlyDigits) {
            $characters = '0123456789';
        } else {
            $characters = '123456789abcdefghABCDEFGH';
        }
        $length = strlen($characters);
        $pin = '';
        for ($i = 0; $i < $this->length; $i++) {
            $pin .= $characters[random_int(0, $length - 1)];
        }

        return $pin;
    }
}
