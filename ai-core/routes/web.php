<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MenuController;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Home'))->name('home');

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
        Route::post('/menu/categories', [MenuController::class, 'storeCategory'])->name('menu.categories.store');
        Route::post('/menu/products', [MenuController::class, 'storeProduct'])->name('menu.products.store');
    });
});