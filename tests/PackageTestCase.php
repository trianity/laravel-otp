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
        // perform environment setup
    }
}
