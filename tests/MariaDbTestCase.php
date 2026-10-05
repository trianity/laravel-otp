<?php

declare(strict_types=1);

namespace Trianity\Otp\Tests;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase;
use Trianity\Otp\Providers\PackageServiceProvider;

abstract class MariaDbTestCase extends TestCase
{
    private bool $databaseWasConfigured = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (getenv('OTP_MARIADB_DATABASE') === false) {
            $this->markTestSkipped('Set OTP_MARIADB_DATABASE to run the MariaDB integration tests.');
        }

        $this->databaseWasConfigured = true;
        config()->set('app.key', '6rE9Nz59bGRbeMATftriyQjrpF7DcOQm');
        DB::statement("SET time_zone = '+00:00'");
        Schema::dropIfExists('otps');
    }

    protected function tearDown(): void
    {
        if ($this->databaseWasConfigured) {
            Schema::dropIfExists('otps');
        }

        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [
            PackageServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('app.timezone', 'UTC');
        $app['config']->set('database.default', 'mariadb');
        $app['config']->set('database.connections.mariadb', [
            'driver' => 'mysql',
            'host' => getenv('OTP_MARIADB_HOST') ?: '127.0.0.1',
            'port' => getenv('OTP_MARIADB_PORT') ?: '3306',
            'database' => getenv('OTP_MARIADB_DATABASE'),
            'username' => getenv('OTP_MARIADB_USERNAME') ?: 'root',
            'password' => getenv('OTP_MARIADB_PASSWORD') ?: '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
        ]);
    }
}
