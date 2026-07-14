<?php

namespace App\Providers;

use App\Modules\Identity\Contracts\SmsGateway;
use App\Modules\Identity\Gateways\NotisendSmsGateway;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SmsGateway::class, NotisendSmsGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
