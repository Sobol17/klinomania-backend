<?php

use App\Modules\CleanerWork\Http\Controllers\CleanerOrderController;
use App\Modules\Identity\Http\Controllers\CleanerAuthController;
use App\Modules\Identity\Http\Controllers\ClientAuthController;
use App\Modules\Orders\Http\Controllers\ClientOrderController;
use App\Modules\Profiles\Http\Controllers\ProfileController;
use App\Modules\Services\Http\Controllers\ServiceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('client/auth/request-code', [ClientAuthController::class, 'requestCode']);
    Route::post('client/auth/verify-code', [ClientAuthController::class, 'verifyCode']);
    Route::post('cleaner/auth/login', [CleanerAuthController::class, 'login']);

    Route::get('services', [ServiceController::class, 'index']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('client/profile', [ProfileController::class, 'client']);
        Route::get('client/orders', [ClientOrderController::class, 'index']);
        Route::post('client/orders/checkout', [ClientOrderController::class, 'checkout']);

        Route::get('cleaner/profile', [ProfileController::class, 'cleaner']);
        Route::get('cleaner/orders/available', [CleanerOrderController::class, 'available']);
        Route::get('cleaner/orders/history', [CleanerOrderController::class, 'history']);
        Route::post('cleaner/orders/{order}/accept', [CleanerOrderController::class, 'accept']);
        Route::post('cleaner/orders/{order}/start', [CleanerOrderController::class, 'start']);
        Route::post('cleaner/orders/{order}/complete', [CleanerOrderController::class, 'complete']);
    });
});
