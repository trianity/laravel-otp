<?php

declare(strict_types=1);

namespace Trianity\Otp\Tests;

use Orchestra\Testbench\TestCase;
use Trianity\Otp\Providers\PackageServiceProvider;

class PackageTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // additional setup
        config()->set('app.key', '6rE9Nz59bGRbeMATftriyQjrpF7DcOQm');
    }

    protected function getPackageProviders($app): array
    {
        return [
            PackageServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        include_once dirname(__FILE__, 2).'/database/migrations/2023_06_01_000001_create_otps_table.php';
    }
}
