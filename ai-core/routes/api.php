<?php

use App\Http\Controllers\Api\QrBranchAgentController;
use App\Http\Controllers\Api\QrMenuController;
use App\Http\Middleware\AuthenticateQrBranchAgent;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['status' => 'ok', 'service' => 'nexora-qr-cloud']));

Route::prefix('qr/v1')->middleware('throttle:60,1')->group(function () {
    Route::get('/menus/{slug}', [QrMenuController::class, 'show']);
    Route::post('/menus/{slug}/orders', [QrMenuController::class, 'store'])->middleware('throttle:10,1');
});

Route::prefix('qr/v1/agent')->middleware([AuthenticateQrBranchAgent::class, 'throttle:120,1'])->group(function () {
    Route::put('/menu', [QrBranchAgentController::class, 'publishMenu']);
    Route::get('/orders/pending', [QrBranchAgentController::class, 'pendingOrders']);
    Route::post('/orders/{orderId}/acknowledge', [QrBranchAgentController::class, 'acknowledge'])->whereNumber('orderId');
});
