<?php

declare(strict_types=1);

namespace Trianity\Otp\Tests;

use DateTimeImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Trianity\Otp\Contracts\OtpClock;
use Trianity\Otp\Facades\Otp;
use Trianity\Otp\Models\Otp as OtpModel;
use Trianity\Otp\OtpGenerator;

final class MutableOtpClock implements OtpClock
{
    public function __construct(private DateTimeImmutable $currentTime) {}

    public function now(): DateTimeImmutable
    {
        return $this->currentTime;
    }

    public function advance(string $modifier): void
    {
        $nextTime = $this->currentTime->modify($modifier);

        if (! $nextTime instanceof DateTimeImmutable) {
            throw new \RuntimeException("Invalid clock modifier: {$modifier}");
        }

        $this->currentTime = $nextTime;
    }
}

final class GenerateOtpWithClockJob
{
    public function __construct(private readonly string $identifier) {}

    public function handle(OtpGenerator $generator): void
    {
        $generator->generate($this->identifier);
    }
}

function bindOtpClock(MutableOtpClock $clock): void
{
    app()->instance(OtpClock::class, $clock);
    Otp::clearResolvedInstance('otp');
}

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-01 18:05:00', 'UTC'));
});

afterEach(function () {
    Carbon::setTestNow();
    app()->forgetInstance(OtpClock::class);
    Otp::clearResolvedInstance('otp');
});

it('uses an overridden clock without changing the global Carbon clock', function () {
    $clock = new MutableOtpClock(new DateTimeImmutable('2026-10-05 21:21:35 UTC'));
    bindOtpClock($clock);

    Otp::generate('custom-clock-generation');
    $storedOtp = OtpModel::query()->where('identifier', 'custom-clock-generation')->firstOrFail();

    expect($storedOtp->generated_at->format('Y-m-d H:i:s'))->toBe('2026-10-05 21:21:35')
        ->and(Carbon::now()->format('Y-m-d H:i:s'))->toBe('2026-11-01 18:05:00');
});

it('keeps the default Laravel clock and existing resolution APIs', function () {
    $clock = app(OtpClock::class);

    expect($clock)->toBeInstanceOf(OtpClock::class)
        ->and($clock->now())->toBeInstanceOf(DateTimeImmutable::class)
        ->and($clock->now()->format('Y-m-d H:i:s'))->toBe('2026-11-01 18:05:00')
        ->and(app('otp'))->toBeInstanceOf(OtpGenerator::class)
        ->and(app(OtpGenerator::class))->toBeInstanceOf(OtpGenerator::class);

    Otp::generate('default-clock');
    $directGenerator = new OtpGenerator;
    $directGenerator->generate('direct-generator');

    expect(OtpModel::query()->where('identifier', 'default-clock')->value('generated_at')->format('Y-m-d H:i:s'))
        ->toBe('2026-11-01 18:05:00')
        ->and(OtpModel::query()->where('identifier', 'direct-generator')->value('generated_at')->format('Y-m-d H:i:s'))
        ->toBe('2026-11-01 18:05:00');

    Carbon::setTestNow(Carbon::parse('2026-11-01 18:06:00', 'UTC'));

    expect($clock->now()->format('Y-m-d H:i:s'))->toBe('2026-11-01 18:06:00');
});

it('uses later clock values for regeneration without counter drift', function () {
    $clock = new MutableOtpClock(new DateTimeImmutable('2026-10-05 21:21:35 UTC'));
    bindOtpClock($clock);
    Otp::generate('custom-clock-regeneration');

    $clock->advance('+26 seconds');
    Otp::validate('custom-clock-regeneration', 'wrong-token');
    $afterAttempt = OtpModel::query()->where('identifier', 'custom-clock-regeneration')->firstOrFail();

    expect($afterAttempt->generated_at->format('Y-m-d H:i:s'))->toBe('2026-10-05 21:21:35')
        ->and($afterAttempt->no_times_attempted)->toBe(1);

    $clock->advance('+34 seconds');
    Otp::generate('custom-clock-regeneration');
    app(OtpGenerator::class)->generate('custom-clock-service-resolution');
    $afterRegeneration = OtpModel::query()->where('identifier', 'custom-clock-regeneration')->firstOrFail();
    $serviceGenerated = OtpModel::query()->where('identifier', 'custom-clock-service-resolution')->firstOrFail();

    expect($afterRegeneration->generated_at->format('Y-m-d H:i:s'))->toBe('2026-10-05 21:22:35')
        ->and($afterRegeneration->no_times_generated)->toBe(2)
        ->and($serviceGenerated->generated_at->format('Y-m-d H:i:s'))->toBe('2026-10-05 21:22:35')
        ->and(Carbon::now()->format('Y-m-d H:i:s'))->toBe('2026-11-01 18:05:00');
});

it('uses the overridden binding in console and queue entry points', function () {
    $clock = new MutableOtpClock(new DateTimeImmutable('2026-10-05 21:21:35 UTC'));
    bindOtpClock($clock);

    Artisan::command('otp-clock:generate-test', function () {
        app(OtpGenerator::class)->generate('custom-clock-console');
    });

    $this->artisan('otp-clock:generate-test')->assertSuccessful();

    $clock->advance('+26 seconds');
    Bus::dispatchSync(new GenerateOtpWithClockJob('custom-clock-queue'));

    expect(OtpModel::query()->where('identifier', 'custom-clock-console')->value('generated_at')->format('Y-m-d H:i:s'))
        ->toBe('2026-10-05 21:21:35')
        ->and(OtpModel::query()->where('identifier', 'custom-clock-queue')->value('generated_at')->format('Y-m-d H:i:s'))
        ->toBe('2026-10-05 21:22:01')
        ->and(Carbon::now()->format('Y-m-d H:i:s'))->toBe('2026-11-01 18:05:00');
});

it('validates against the overridden clock before at and after the expiry boundary', function () {
    $clock = new MutableOtpClock(new DateTimeImmutable('2026-10-05 21:21:35 UTC'));
    bindOtpClock($clock);

    $after26Seconds = Otp::setValidity(1)->generate('clock-after-26-seconds');
    $beforeBoundary = Otp::setValidity(1)->generate('clock-before-boundary');
    $atBoundary = Otp::setValidity(1)->generate('clock-at-boundary');
    $afterBoundary = Otp::setValidity(1)->generate('clock-after-boundary');

    $clock->advance('+26 seconds');
    expect(Otp::validate('clock-after-26-seconds', $after26Seconds->token)->status)->toBeTrue();

    $clock->advance('+33 seconds');
    expect(Otp::validate('clock-before-boundary', $beforeBoundary->token)->status)->toBeTrue();

    $clock->advance('+1 second');
    expect(Otp::validate('clock-at-boundary', $atBoundary->token)->status)->toBeTrue();

    $clock->advance('+1 second');
    expect(Otp::validate('clock-after-boundary', $afterBoundary->token)->status)->toBeFalse()
        ->and(Carbon::now()->format('Y-m-d H:i:s'))->toBe('2026-11-01 18:05:00');
});

it('keeps wrong-code limits and one-time use with the overridden clock', function () {
    $clock = new MutableOtpClock(new DateTimeImmutable('2026-10-05 21:21:35 UTC'));
    bindOtpClock($clock);

    $limited = Otp::generate('custom-clock-attempt-limit');
    expect(Otp::setAllowedAttempts(1)->validate('custom-clock-attempt-limit', 'wrong-token')->status)->toBeFalse()
        ->and(Otp::setAllowedAttempts(1)->validate('custom-clock-attempt-limit', $limited->token)->status)->toBeFalse();

    $singleUse = Otp::generate('custom-clock-single-use');
    expect(Otp::validate('custom-clock-single-use', $singleUse->token)->status)->toBeTrue()
        ->and(Otp::validate('custom-clock-single-use', $singleUse->token)->status)->toBeFalse();
});

it('cleans records according to the overridden clock', function () {
    $clock = new MutableOtpClock(new DateTimeImmutable('2026-10-05 21:21:35 UTC'));
    bindOtpClock($clock);

    Otp::generate('old-by-custom-clock');
    $clock->advance('+30 minutes');
    Otp::generate('valid-by-custom-clock');
    $clock->advance('+2 minutes');
    Otp::generate('cleanup-trigger');

    expect(OtpModel::query()->where('identifier', 'old-by-custom-clock')->exists())->toBeFalse()
        ->and(OtpModel::query()->where('identifier', 'valid-by-custom-clock')->exists())->toBeTrue()
        ->and(Carbon::now()->format('Y-m-d H:i:s'))->toBe('2026-11-01 18:05:00');
});
