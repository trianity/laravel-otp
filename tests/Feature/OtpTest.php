<?php

namespace Trianity\Otp\Tests;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Trianity\Otp\Facades\Otp;
use Trianity\Otp\Models\Otp as OtpModel;
use Trianity\Otp\Rules\OtpRule;

it('can generate and validate otp', function () {
    $identifier = Str::random(12);
    $otp = Otp::generate($identifier);
    $validator = Otp::validate($identifier, $otp->token);

    expect($validator->status)->toBeTrue();
});

it('can validate otp tokens with the validation rule', function () {
    $identifier = Str::random(12);
    $otp = Otp::generate($identifier);

    $validator = Validator::make([
        'otp' => $otp->token,
    ], [
        'otp' => ['required', new OtpRule($identifier)],
    ]);

    expect($validator->passes())->toBeTrue()
        ->and(Otp::validate($identifier, $otp->token)->status)->toBeFalse();
});

it('rejects invalid otp tokens with the validation rule', function () {
    $identifier = Str::random(12);
    Otp::generate($identifier);

    $validator = Validator::make([
        'otp' => 'wrong-token',
    ], [
        'otp' => ['required', new OtpRule($identifier)],
    ]);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('otp'))->toBe(trans('otp::messages.otp_wrong'));
});

it('expires the otp after a successful validation', function () {
    $identifier = Str::random(12);
    $otp = Otp::generate($identifier);

    expect(Otp::validate($identifier, $otp->token)->status)->toBeTrue()
        ->and(Otp::validate($identifier, $otp->token)->status)->toBeFalse();
});

it('stores the otp token as a hash', function () {
    $identifier = Str::random(12);
    $otp = Otp::generate($identifier);
    $storedOtp = OtpModel::query()->where('identifier', $identifier)->firstOrFail();

    expect($storedOtp->token)->not->toBe($otp->token)
        ->and(Hash::check($otp->token, $storedOtp->token))->toBeTrue();
});

it('requires a unique identifier for stored otps', function () {
    $identifier = Str::random(12);
    Otp::generate($identifier);

    expect(fn () => OtpModel::query()->create([
        'identifier' => $identifier,
        'token' => Hash::make('123456'),
        'validity' => config('otp.validity'),
        'generated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('cant able to verify the opt once get expired', function () {
    $identifier = Str::random(12);
    $otp = Otp::generate($identifier);
    $this->travel(config('otp.validity') + 1)->minutes();
    $validator = Otp::validate($identifier, $otp->token);

    expect($validator->status)->toBeFalse();
});

it('able to regenerate and validate the otp', function () {
    $identifier = Str::random(12);
    Otp::generate($identifier);
    $secondTime = Otp::generate($identifier);
    $validator = Otp::validate($identifier, $secondTime->token);

    expect($validator->status)->toBeTrue();
    $this->assertDatabaseCount('otps', 1);
});

it('cant able to generate the otp more than the maximum specified time', function () {
    $identifier = Str::random(12);
    $limit = config('otp.maximumOtpsAllowed');

    for ($i = 0; $i < $limit; $i++) {
        Otp::generate($identifier);
    }
    $otp = Otp::generate($identifier);

    expect($otp->status)->toBeFalse();
});

it('can delete the otps after spceifed amount of time', function () {
    $identifier = Str::random(12);
    $otp = Otp::generate($identifier);
    $this->travel(config('otp.deleteOldOtps'))->minutes();
    $validator = Otp::validate($identifier, $otp->token);
    $this->assertEquals($validator->status, false);
    $this->assertDatabaseCount('otps', 1);
    Otp::generate(Str::random(13));
    $this->assertDatabaseCount('otps', 1);
    $this->travelBack();
});

it('does not delete regenerated otps based on their original creation time', function () {
    $identifier = Str::random(12);
    Otp::generate($identifier);
    $storedOtp = OtpModel::query()->where('identifier', $identifier)->firstOrFail();

    $this->travel(20)->minutes();
    Otp::generate($identifier);
    $this->travel(12)->minutes();
    Otp::generate(Str::random(13));

    expect(OtpModel::query()->whereKey($storedOtp->getKey())->exists())->toBeTrue();
});

it('cant able to verify the otp once reach the maximum allowedAttempts', function () {
    $identifier = Str::random(12);
    $otp = Otp::generate($identifier);
    $allowedAttempts = config('otp.allowedAttempts');
    for ($i = 0; $i < $allowedAttempts; $i++) {
        Otp::validate($identifier, 'wrongToken');
    }
    $validator = Otp::validate($identifier, $otp->token);
    expect($validator->status)->toBeFalse();
});

it('can set custom validity time and maximum otps allowed numbers', function () {
    $identifier = Str::random(12);
    Otp::setValidity(30)
        ->generate($identifier);

    $this->assertDatabaseHas('otps', [
        'validity' => 30,
    ]);
    $identifier = Str::random(11);
    $maximumOtpsAllowed = 10;
    for ($i = 0; $i < $maximumOtpsAllowed; $i++) {
        $otp = Otp::setMaximumOtpsAllowed($maximumOtpsAllowed)
            ->generate($identifier);
        expect($otp->status)->toBeTrue();
    }
});

it('can set custom number of allowed attempts', function () {
    $identifier = Str::random(12);
    $otp = Otp::generate($identifier);
    $allowedAttempts = 11;
    for ($i = 0; $i < $allowedAttempts - 1; $i++) {
        Otp::setAllowedAttempts($allowedAttempts)
            ->validate($identifier, 'wrongToken');
    }
    $validator = Otp::setAllowedAttempts($allowedAttempts)
        ->validate($identifier, $otp->token);
    expect($validator->status)->toBeTrue();
});

it('can set custom otp length', function () {
    $identifier = Str::random(12);
    $otp = Otp::setLength(8)
        ->generate($identifier);
    expect(strlen($otp->token))->toBe(8);
});

it('resets custom otp length after a generate call', function () {
    $customLengthOtp = Otp::setLength(8)
        ->generate(Str::random(12));
    $defaultLengthOtp = Otp::generate(Str::random(12));

    expect(strlen($customLengthOtp->token))->toBe(8)
        ->and(strlen($defaultLengthOtp->token))->toBe(config('otp.length'));
});

it('can keep the same stored token valid on second time onwards', function () {
    $identifier = Str::random(12);
    $otp1 = Otp::generate($identifier);
    $otp2 = Otp::setUseSameToken(true)->generate($identifier);

    expect($otp2->token)->toBeNull()
        ->and(Otp::validate($identifier, $otp1->token)->status)->toBeTrue();
});

it('can get expired at time', function () {
    $identifier = Str::random(12);
    Otp::generate($identifier);
    $expires = Otp::expiredAt($identifier);
    // The default expiry time: 30 min.
    expect(intval(round($expires->expired_at->diffInMinutes())))->toBe(-30);
});
