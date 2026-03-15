<?php

use App\Http\Controllers\AppMaxWebhookController;
use App\Http\Controllers\OrderCheckoutController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/webhooks/appmax', AppMaxWebhookController::class)
    ->name('webhooks.appmax')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::middleware('throttle:60,1')->group(function () {
    Route::get('/r/{token}', [OrderCheckoutController::class, 'show'])->name('checkout.show');
    Route::post('/r/{order:token}', [OrderCheckoutController::class, 'store'])->name('checkout.store');
    Route::get('/r/{order:token}/payment-callback', [OrderCheckoutController::class, 'paymentCallback'])->name('checkout.payment-callback');
});
