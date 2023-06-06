<?php

declare(strict_types=1);

namespace Trianity\Otp\Providers;

use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\ServiceProvider;

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
    }
}
