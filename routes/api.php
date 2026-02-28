<?php

use App\Http\Controllers\Api\CreateOrderController;
use App\Http\Middleware\VerifyApiKey;
use Illuminate\Support\Facades\Route;

Route::post('/orders', CreateOrderController::class)
    ->middleware(['throttle:api', VerifyApiKey::class]);
