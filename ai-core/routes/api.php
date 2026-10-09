<?php

use App\Http\Controllers\Api\QrBranchAgentController;
use App\Http\Controllers\Api\QrMenuController;
use App\Http\Controllers\Api\QrOwnerAuthController;
use App\Http\Controllers\Api\QrOwnerDashboardController;
use App\Http\Middleware\AuthenticateQrBranchAgent;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['status' => 'ok', 'service' => 'nexora-qr-cloud']));

Route::prefix('qr/v1/owner')->group(function () {
    Route::post('/login', [QrOwnerAuthController::class, 'login'])->middleware('throttle:10,1');
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [QrOwnerAuthController::class, 'me']);
        Route::post('/logout', [QrOwnerAuthController::class, 'logout']);
        Route::get('/dashboard', [QrOwnerDashboardController::class, 'index']);
    });
});

Route::prefix('qr/v1')->middleware('throttle:60,1')->group(function () {
    Route::get('/menus/{slug}', [QrMenuController::class, 'show']);
    Route::post('/menus/{slug}/orders', [QrMenuController::class, 'store'])->middleware('throttle:10,1');
});

Route::prefix('qr/v1/agent')->middleware([AuthenticateQrBranchAgent::class, 'throttle:120,1'])->group(function () {
    Route::put('/menu', [QrBranchAgentController::class, 'publishMenu']);
    Route::get('/orders/pending', [QrBranchAgentController::class, 'pendingOrders']);
    Route::post('/orders/{orderId}/acknowledge', [QrBranchAgentController::class, 'acknowledge'])->whereNumber('orderId');
    Route::post('/orders/{orderId}/resolve', [QrBranchAgentController::class, 'resolve'])->whereNumber('orderId');
});
