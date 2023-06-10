<?php

declare(strict_types=1);

namespace Trianity\Otp;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
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

    public function __construct()
    {
        $this->length = config('otp.length');
        $this->onlyDigits = config('otp.onlyDigits');
        $this->useSameToken = config('otp.useSameToken');
        $this->validity = config('otp.validity');
        $this->deleteOldOtps = config('otp.deleteOldOtps');
        $this->maximumOtpsAllowed = config('otp.maximumOtpsAllowed');
        $this->allowedAttempts = config('otp.allowedAttempts');
    }

    /**
     * When a method is called, look for the 'set' prefix and attempt to set the
     * matching property to the value passed to the method and return a chainable
     * object to the caller.
     *
     * @param  array<int, string>  $params
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
        $this->deleteOldOtps();

        $otp = OtpModel::where('identifier', $identifier)->first();

        if (! $otp instanceof OtpModel) {
            $otp = OtpModel::create([
                'identifier' => $identifier,
                'token' => $this->createPin(),
                'validity' => $this->validity,
                'generated_at' => Carbon::now(),
            ]);

            $otp->increment('no_times_generated');

            return (object) [
                'status' => true,
                'token' => $otp->token,
                'message' => trans('otp::messages.otp_generated'),
                'code' => 0,
            ];
        }

        return $this->updateOtp($otp, $identifier);
    }

    public function validate(string $identifier, string $token): object
    {
        $otp = OtpModel::where('identifier', $identifier)->first();

        if (! $otp instanceof OtpModel) {
            return (object) [
                'status' => false,
                'message' => trans('otp::messages.otp_missing'),
                'code' => 1,
            ];
        }

        if ($otp->isExpired()) {
            return (object) [
                'status' => false,
                'message' => trans('otp::messages.otp_expired'),
                'code' => 1,
            ];
        }

        if ($otp->no_times_attempted === $this->allowedAttempts) {
            return (object) [
                'status' => false,
                'message' => trans('otp::messages.otp_max_reached'),
                'code' => 3,
            ];
        }

        $otp->increment('no_times_attempted');

        if (Str::of($otp->token)->exactly($token)) {
            return (object) [
                'status' => true,
                'message' => trans('otp::messages.otp_valid'),
                'code' => 0,
            ];
        }

        return (object) [
            'status' => false,
            'message' => trans('otp::messages.otp_wrong'),
            'code' => 2,
        ];
    }

    public function expiredAt(string $identifier): object
    {
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
    }

    protected function updateOtp(OtpModel $otp, string $identifier): object
    {
        if ($otp->no_times_generated === $this->maximumOtpsAllowed) {
            return (object) [
                'status' => false,
                'message' => trans('otp::messages.otp_max_gen'),
                'code' => 3,
            ];
        }

        $otp->update([
            'identifier' => $identifier,
            'token' => $this->useSameToken ? $otp->token : $this->createPin(),
            'validity' => $this->validity,
            'generated_at' => Carbon::now(),
        ]);

        $otp->increment('no_times_generated');

        return (object) [
            'status' => true,
            'token' => $otp->token,
            'message' => trans('otp::messages.otp_generated'),
            'code' => 0,
        ];
    }

    private function deleteOldOtps(): void
    {
        OtpModel::where('expired', true)
            ->orWhere('created_at', '<', Carbon::now()->subMinutes($this->deleteOldOtps))
            ->delete();
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
            $pin .= $characters[rand(0, $length - 1)];
        }

        return $pin;
    }
}
