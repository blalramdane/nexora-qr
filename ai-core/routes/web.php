<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\PublicMenuController;
use App\Http\Controllers\PublicOrderController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\TableController;
use App\Http\Controllers\ModifierController;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Home'))->name('home');
Route::get('/m/{restaurantSlug}', PublicMenuController::class)->name('public.menu');
Route::get('/order/{orderNumber}', PublicOrderController::class)->name('public.order');
Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->name('register.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::middleware(ResolveTenant::class)->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
        Route::get('/menu', [MenuController::class, 'index'])->name('menu');
        Route::get('/menu/modifiers', [ModifierController::class, 'index'])->name('menu.modifiers');
        Route::post('/menu/modifier-groups', [ModifierController::class, 'storeGroup'])->name('menu.modifier-groups.store');
        Route::post('/menu/modifiers', [ModifierController::class, 'storeModifier'])->name('menu.modifiers.store');
        Route::post('/menu/modifier-groups/attach', [ModifierController::class, 'attach'])->name('menu.modifier-groups.attach');
        Route::get('/orders', [OrderController::class, 'index'])->name('orders');
        Route::post('/orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status');
        Route::get('/tables', [TableController::class, 'index'])->name('tables');
        Route::post('/tables', [TableController::class, 'store'])->name('tables.store');
        Route::post('/tables/{table}/toggle', [TableController::class, 'toggle'])->name('tables.toggle');
        Route::post('/menu/categories', [MenuController::class, 'storeCategory'])->name('menu.categories.store');
        Route::post('/menu/products', [MenuController::class, 'storeProduct'])->name('menu.products.store');
    });
});