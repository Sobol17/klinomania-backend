<?php

namespace App\Providers;

use App\Modules\Identity\Contracts\SmsGateway;
use App\Modules\Identity\Gateways\NotisendSmsGateway;
use App\Modules\Notifications\Contracts\PushGateway;
use App\Modules\Notifications\Events\OrderStatusChanged;
use App\Modules\Notifications\Gateways\FirebasePushGateway;
use App\Modules\Notifications\Listeners\SendOrderStatusPush;
use App\Modules\Payments\Contracts\TBankGateway;
use App\Modules\Payments\Gateways\HttpTBankGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Factory;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SmsGateway::class, NotisendSmsGateway::class);
        $this->app->singleton(Messaging::class, function (): Messaging {
            $credentialsPath = config('firebase.credentials_path');

            if (! is_string($credentialsPath) || $credentialsPath === '' || ! is_file($credentialsPath)) {
                throw new RuntimeException('Firebase service-account credentials are not configured.');
            }

            return (new Factory)
                ->withServiceAccount($credentialsPath)
                ->createMessaging();
        });
        $this->app->bind(PushGateway::class, FirebasePushGateway::class);
        $this->app->bind(TBankGateway::class, HttpTBankGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(OrderStatusChanged::class, SendOrderStatusPush::class);

        RateLimiter::for('order-checkout', function (Request $request): Limit {
            return Limit::perMinute(10)
                ->by((string) ($request->user()?->id ?? $request->ip()))
                ->response(fn () => response()->json([
                    'message' => 'Too many order creation attempts.',
                    'code' => 'rate_limited',
                ], 429));
        });
    }
}
