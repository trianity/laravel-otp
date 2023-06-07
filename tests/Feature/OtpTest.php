<?php

namespace Trianity\Otp\Tests;

use Illuminate\Support\Str;
use Trianity\Otp\Facades\Otp;

it('can generate and validate otp', function () {
   $identifier = Str::random(12);
   $otp = Otp::generate($identifier);
   $validator = Otp::validate($identifier, $otp->token);

   expect($validator->status)->toBeTrue();
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
   $validator = Otp::validate($identifier, $otp->token);
   expect($validator->status)->toBeTrue();
});

it('can set custom otp length', function () {
   $identifier = Str::random(12);
   $otp = Otp::setLength(8)
      ->generate($identifier);
   expect(strlen($otp->token))->toBe(8);
});

it('can get the same token on second time onwards', function () {
   $identifier = Str::random(12);
   $otp1 = Otp::generate($identifier);
   $otp2 = Otp::setUseSameToken(true)->generate($identifier);
   expect($otp1->token)->toBe($otp2->token);
});

it('can get expired at time', function () {
   $identifier = Str::random(12);
   Otp::generate($identifier);
   $expires = Otp::expiredAt($identifier);
   //The default expiry time: 30 min.
   expect($expires->expired_at->diffInMinutes())->toBe(29);
});

