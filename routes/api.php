<?php

use App\Modules\CleanerWork\Http\Controllers\CleanerOrderController;
use App\Modules\Identity\Http\Controllers\CleanerAuthController;
use App\Modules\Identity\Http\Controllers\ClientAuthController;
use App\Modules\Notifications\Http\Controllers\ClientPushTokenController;
use App\Modules\Orders\Http\Controllers\ClientOrderController;
use App\Modules\Payments\Http\Controllers\ClientPaymentController;
use App\Modules\Payments\Http\Controllers\TBankNotificationController;
use App\Modules\Profiles\Http\Controllers\ProfileController;
use App\Modules\Services\Http\Controllers\ClientServiceController;
use App\Modules\Services\Http\Controllers\ServiceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('client/auth/request-code', [ClientAuthController::class, 'requestCode']);
    Route::post('client/auth/verify-code', [ClientAuthController::class, 'verifyCode']);
    Route::post('cleaner/auth/login', [CleanerAuthController::class, 'login']);

    Route::get('services', [ServiceController::class, 'index']);
    Route::post('payments/tbank/notifications', [TBankNotificationController::class, 'store']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('client/home-summary', [ClientServiceController::class, 'homeSummary']);
        Route::get('client/services', [ClientServiceController::class, 'index']);
        Route::get('client/services/{serviceId}', [ClientServiceController::class, 'show']);
        Route::post('client/orders', [ClientServiceController::class, 'createOrder'])->middleware('throttle:order-checkout');
        Route::get('client/profile', [ProfileController::class, 'client']);
        Route::patch('client/profile', [ProfileController::class, 'updateClient']);
        Route::post('client/push-token', [ClientPushTokenController::class, 'store']);
        Route::get('client/orders', [ClientOrderController::class, 'index']);
        Route::post('client/orders/{order}/payment', [ClientPaymentController::class, 'store']);
        Route::post('client/orders/{order}/cancel', [ClientOrderController::class, 'cancel']);

        Route::get('cleaner/profile', [ProfileController::class, 'cleaner']);
        Route::get('cleaner/orders', [CleanerOrderController::class, 'orders']);
        Route::get('cleaner/orders/available', [CleanerOrderController::class, 'available']);
        Route::get('cleaner/orders/history', [CleanerOrderController::class, 'history']);
        Route::get('cleaner/orders/{order}', [CleanerOrderController::class, 'show']);
        Route::patch('cleaner/orders/{order}/checklist/{item}', [CleanerOrderController::class, 'updateChecklistItem']);
        Route::post('cleaner/orders/{order}/accept', [CleanerOrderController::class, 'accept']);
        Route::post('cleaner/orders/{order}/start', [CleanerOrderController::class, 'start']);
        Route::post('cleaner/orders/{order}/complete', [CleanerOrderController::class, 'complete']);
    });
});
