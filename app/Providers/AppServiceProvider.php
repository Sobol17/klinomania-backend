<?php

namespace App\Providers;

use App\Modules\Identity\Contracts\SmsGateway;
use App\Modules\Identity\Gateways\NoticendSmsGateway;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SmsGateway::class, NoticendSmsGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
