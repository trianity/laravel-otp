<?php

declare(strict_types=1);

namespace Trianity\Otp\Tests\Integration;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Trianity\Otp\Facades\Otp;
use Trianity\Otp\Tests\MariaDbTestCase;

uses(MariaDbTestCase::class);

afterEach(function () {
    Carbon::setTestNow();
});

function createFaultyOtpsTable(): void
{
    DB::unprepared(<<<'SQL'
        CREATE TABLE otps (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            identifier VARCHAR(255) NOT NULL UNIQUE,
            token VARCHAR(255) NOT NULL,
            validity INT NOT NULL,
            expired TINYINT(1) NOT NULL DEFAULT 0,
            no_times_generated INT NOT NULL DEFAULT 0,
            no_times_attempted INT NOT NULL DEFAULT 0,
            generated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL
        )
        SQL);
}

function createCorrectLegacyOtpsTable(): void
{
    DB::unprepared(<<<'SQL'
        CREATE TABLE otps (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            identifier VARCHAR(255) NOT NULL UNIQUE,
            token VARCHAR(255) NOT NULL,
            validity INT NOT NULL,
            expired TINYINT(1) NOT NULL DEFAULT 0,
            no_times_generated INT NOT NULL DEFAULT 0,
            no_times_attempted INT NOT NULL DEFAULT 0,
            generated_at TIMESTAMP NOT NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL
        )
        SQL);
}

function generatedAtDefinition(): object
{
    return DB::table('information_schema.columns')
        ->select(['DATA_TYPE', 'IS_NULLABLE', 'COLUMN_DEFAULT', 'EXTRA'])
        ->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
        ->where('TABLE_NAME', 'otps')
        ->where('COLUMN_NAME', 'generated_at')
        ->firstOrFail();
}

function migration(string $filename): object
{
    return require dirname(__DIR__, 2).'/database/migrations/'.$filename;
}

it('preserves generated at during generation on the reported faulty schema', function () {
    createFaultyOtpsTable();

    $applicationNow = Carbon::parse('2030-01-02 03:04:05', 'UTC');
    Carbon::setTestNow($applicationNow);
    $identifier = Str::random(12);

    Otp::generate($identifier);

    $storedGeneratedAt = DB::table('otps')
        ->where('identifier', $identifier)
        ->value('generated_at');

    expect((string) $storedGeneratedAt)->toBe($applicationNow->format('Y-m-d H:i:s'));

    Carbon::setTestNow($applicationNow->copy()->addMinutes(5));
    Otp::validate($identifier, 'wrong-token');

    expect((string) DB::table('otps')->where('identifier', $identifier)->value('generated_at'))
        ->toBe($applicationNow->format('Y-m-d H:i:s'));

    $regeneratedAt = $applicationNow->copy()->addMinutes(15);
    Carbon::setTestNow($regeneratedAt);
    $regeneratedOtp = Otp::generate($identifier);

    expect((string) DB::table('otps')->where('identifier', $identifier)->value('generated_at'))
        ->toBe($regeneratedAt->format('Y-m-d H:i:s'));

    Carbon::setTestNow($applicationNow->copy()->addMinutes(20));
    Otp::validate($identifier, $regeneratedOtp->token);

    expect((string) DB::table('otps')->where('identifier', $identifier)->value('generated_at'))
        ->toBe($regeneratedAt->format('Y-m-d H:i:s'));
});

it('creates a database-managed-update-free column with implicit timestamp defaults enabled', function () {
    DB::statement('SET SESSION explicit_defaults_for_timestamp = OFF');

    try {
        migration('2023_06_01_000001_create_otps_table.php')->up();
    } finally {
        DB::statement('SET SESSION explicit_defaults_for_timestamp = ON');
    }

    $definition = generatedAtDefinition();

    expect(strtolower($definition->DATA_TYPE))->toBe('datetime')
        ->and($definition->IS_NULLABLE)->toBe('NO')
        ->and($definition->COLUMN_DEFAULT)->toBeNull()
        ->and(strtolower($definition->EXTRA))->not->toContain('on update');
});

it('repairs the faulty schema without changing records or generated timestamps', function () {
    createFaultyOtpsTable();
    $generatedAt = '2035-06-07 08:09:10';

    DB::table('otps')->insert([
        'identifier' => 'existing-faulty-record',
        'token' => 'hash',
        'validity' => 30,
        'generated_at' => $generatedAt,
    ]);

    migration('2026_10_05_000001_remove_generated_at_automatic_update.php')->up();
    DB::table('otps')->where('identifier', 'existing-faulty-record')->update([
        'no_times_attempted' => 1,
    ]);

    $definition = generatedAtDefinition();
    $record = DB::table('otps')->where('identifier', 'existing-faulty-record')->first();

    expect(strtolower($definition->DATA_TYPE))->toBe('datetime')
        ->and(strtolower($definition->EXTRA))->not->toContain('on update')
        ->and($record)->not->toBeNull()
        ->and((string) $record->generated_at)->toBe($generatedAt)
        ->and($record->no_times_attempted)->toBe(1);
});

it('safely upgrades an already correct schema and keeps rollback safe', function () {
    createCorrectLegacyOtpsTable();
    $generatedAt = '2036-07-08 09:10:11';

    DB::table('otps')->insert([
        'identifier' => 'existing-correct-record',
        'token' => 'hash',
        'validity' => 30,
        'generated_at' => $generatedAt,
    ]);

    $correction = migration('2026_10_05_000001_remove_generated_at_automatic_update.php');
    $correction->up();
    $correction->up();
    $correction->down();

    $definition = generatedAtDefinition();
    $record = DB::table('otps')->where('identifier', 'existing-correct-record')->first();

    expect(strtolower($definition->DATA_TYPE))->toBe('datetime')
        ->and(strtolower($definition->EXTRA))->not->toContain('on update')
        ->and((string) $record->generated_at)->toBe($generatedAt);
});
