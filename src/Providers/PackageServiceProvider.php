<?php

declare(strict_types=1);

namespace Trianity\Otp\Providers;

use Illuminate\Contracts\Container\Container;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\ServiceProvider;
use Trianity\Otp\OtpGenerator;

class PackageServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        AboutCommand::add(
            'Laravel OTP Package',
            fn () => ['Version' => '10.0.1']
        );

        $this->publishes([
            dirname(__FILE__, 3).'/config/otp.php' => base_path('config/otp.php'),
        ], 'otp-config');

        $this->loadMigrationsFrom(dirname(__FILE__, 3).'/database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([

            ]);
        }
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            dirname(__FILE__, 3).'/config/otp.php',
            'otp'
        );

        $this->registerBindings();
    }

    /**
     * @return array
     */
    public function provides(): array
    {
        return [
            'otp',
        ];
    }

    protected function registerBindings(): void
    {
        $this->app->singleton('otp', function () {
            return new OtpGenerator();
        });

        $this->app->alias('otp', OtpGenerator::class);
    }
}
