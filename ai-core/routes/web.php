<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\MenuCategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductVariantController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::middleware('tenant')->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
        Route::get('/menus', [MenuController::class, 'index'])->name('menus.index');
        Route::post('/menus', [MenuController::class, 'store'])->name('menus.store');
        Route::get('/menus/{menu}', [MenuController::class, 'show'])->name('menus.show');
        Route::put('/menus/{menu}', [MenuController::class, 'update'])->name('menus.update');
        Route::delete('/menus/{menu}', [MenuController::class, 'destroy'])->name('menus.destroy');

        Route::post('/menus/{menu}/categories', [MenuCategoryController::class, 'store'])->name('menus.categories.store');
        Route::put('/menus/{menu}/categories/{category}', [MenuCategoryController::class, 'update'])->name('menus.categories.update');
        Route::delete('/menus/{menu}/categories/{category}', [MenuCategoryController::class, 'destroy'])->name('menus.categories.destroy');
        Route::post('/menus/{menu}/categories/reorder', [MenuCategoryController::class, 'reorder'])->name('menus.categories.reorder');

        Route::post('/menus/{menu}/categories/{category}/products', [ProductController::class, 'store'])->name('menus.products.store');
        Route::put('/menus/{menu}/categories/{category}/products/{product}', [ProductController::class, 'update'])->name('menus.products.update');
        Route::delete('/menus/{menu}/categories/{category}/products/{product}', [ProductController::class, 'destroy'])->name('menus.products.destroy');
        Route::post('/menus/{menu}/categories/{category}/products/reorder', [ProductController::class, 'reorder'])->name('menus.products.reorder');

        Route::post('/menus/{menu}/categories/{category}/products/{product}/variants', [ProductVariantController::class, 'store'])->name('menus.product-variants.store');
        Route::put('/menus/{menu}/categories/{category}/products/{product}/variants/{variant}', [ProductVariantController::class, 'update'])->name('menus.product-variants.update');
        Route::delete('/menus/{menu}/categories/{category}/products/{product}/variants/{variant}', [ProductVariantController::class, 'destroy'])->name('menus.product-variants.destroy');
        Route::post('/menus/{menu}/categories/{category}/products/{product}/variants/reorder', [ProductVariantController::class, 'reorder'])->name('menus.product-variants.reorder');

        Route::post('/menus/{menu}/categories/{category}/products/{product}/modifiers', [ModifierController::class, 'store'])->name('menus.modifiers.store');
        Route::put('/menus/{menu}/categories/{category}/products/{product}/modifiers/{modifier}', [ModifierController::class, 'update'])->name('menus.modifiers.update');
        Route::delete('/menus/{menu}/categories/{category}/products/{product}/modifiers/{modifier}', [ModifierController::class, 'destroy'])->name('menus.modifiers.destroy');
        Route::post('/menus/{menu}/categories/{category}/products/{product}/modifiers/reorder', [ModifierController::class, 'reorder'])->name('menus.modifiers.reorder');
    });
});

Route::redirect('/', '/dashboard');
