<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\MenuCategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductVariantController;
use App\Http\Controllers\ModifierController;
use App\Http\Controllers\ProductImageController;
use App\Http\Controllers\PublicMenuController;
use App\Http\Controllers\PublicOrderController;
use App\Http\Controllers\AiWaiterController;
use App\Http\Controllers\LocalDirectoryController;
use App\Http\Controllers\LocalScoutController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\RestaurantSettingsController;
use App\Http\Controllers\OrderTrackingController;
use App\Http\Controllers\DeliveryRiderController;
use App\Http\Controllers\RestaurantDeliveryController;
use App\Http\Controllers\DeliveryTripController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\DeliveryCashSettlementController;
use App\Http\Controllers\DeliveryRiderLedgerController;
use App\Http\Controllers\DeliveryShiftClosingController;
use App\Http\Controllers\FinancialReportController;
use App\Http\Controllers\RestaurantExpenseController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\MenuImportController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login.store');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1')->name('register.store');
    Route::get('/delivery/register', [DeliveryRiderController::class, 'showRegister'])->name('delivery.register');
    Route::post('/delivery/register', [DeliveryRiderController::class, 'register'])->middleware('throttle:5,1')->name('delivery.register.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/delivery', [DeliveryRiderController::class, 'dashboard'])->name('delivery.dashboard');
    Route::patch('/delivery/marketplace-availability', [DeliveryRiderController::class, 'updateMarketplaceAvailability'])->name('delivery.marketplace-availability');
    Route::post('/delivery/available-orders/{order}/claim', [DeliveryRiderController::class, 'claimAvailableOrder'])->middleware('throttle:30,1')->name('delivery.available-orders.claim');
    Route::post('/delivery/invitations/{restaurant}', [DeliveryRiderController::class, 'respondToInvite'])->name('delivery.invitations.respond');
    Route::patch('/delivery/orders/{order}/status', [DeliveryRiderController::class, 'updateDeliveryStatus'])->name('delivery.orders.status');
    Route::patch('/delivery/trips/{trip}/start', [DeliveryTripController::class, 'riderStart'])->name('delivery.trips.rider-start');

    Route::middleware('tenant')->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
        Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
        Route::post('/orders/manual', [OrderController::class, 'storeManual'])->name('orders.manual.store');
        Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status');
        Route::patch('/orders/{order}/payment', [OrderController::class, 'updatePaymentStatus'])->name('orders.payment.update');
        Route::get('/delivery-management', [RestaurantDeliveryController::class, 'index'])->name('restaurant.delivery.index');
        Route::get('/delivery-trips', [DeliveryTripController::class, 'index'])->name('delivery.trips.index');
        Route::post('/delivery-trips', [DeliveryTripController::class, 'store'])->name('delivery.trips.store');
        Route::patch('/delivery-trips/{trip}/start', [DeliveryTripController::class, 'start'])->name('delivery.trips.start');
        Route::get('/delivery-settlements', [DeliveryCashSettlementController::class, 'index'])->name('delivery.settlements.index');
        Route::post('/delivery-settlements', [DeliveryCashSettlementController::class, 'store'])->name('delivery.settlements.store');
        Route::get('/delivery-ledger', [DeliveryRiderLedgerController::class, 'index'])->name('delivery.ledger.index');
        Route::post('/delivery-ledger', [DeliveryRiderLedgerController::class, 'store'])->name('delivery.ledger.store');
        Route::get('/delivery-shift-closings', [DeliveryShiftClosingController::class, 'index'])->name('delivery.shift-closings.index');
        Route::post('/delivery-shift-closings', [DeliveryShiftClosingController::class, 'store'])->name('delivery.shift-closings.store');
        Route::get('/reports/financial', [FinancialReportController::class, 'index'])->name('reports.financial.index');
        Route::get('/reports/financial/export', [FinancialReportController::class, 'export'])->name('reports.financial.export');
        Route::get('/expenses', [RestaurantExpenseController::class, 'index'])->name('expenses.index');
        Route::post('/expenses', [RestaurantExpenseController::class, 'store'])->name('expenses.store');
        Route::delete('/expenses/{expense}', [RestaurantExpenseController::class, 'destroy'])->name('expenses.destroy');
        Route::get('/purchases', [PurchaseController::class, 'index'])->name('purchases.index');
        Route::post('/suppliers', [PurchaseController::class, 'storeSupplier'])->name('suppliers.store');
        Route::post('/purchases', [PurchaseController::class, 'store'])->name('purchases.store');
        Route::post('/purchases/{purchase}/payments', [PurchaseController::class, 'recordPayment'])->name('purchases.payments.store');
        Route::post('/delivery-management/invitations', [RestaurantDeliveryController::class, 'invite'])->name('restaurant.delivery.invite');
        Route::patch('/delivery-management/riders/{rider}', [RestaurantDeliveryController::class, 'updateRider'])->name('restaurant.delivery.riders.update');
        Route::patch('/orders/{order}/delivery', [RestaurantDeliveryController::class, 'assign'])->name('orders.delivery.assign');
        Route::get('/settings/orders', [RestaurantSettingsController::class, 'edit'])->name('restaurant.settings.edit');
        Route::put('/settings/orders', [RestaurantSettingsController::class, 'update'])->name('restaurant.settings.update');
        Route::get('/menus/{menu}/import', [MenuImportController::class, 'show'])->name('menus.import.show');
        Route::post('/menus/{menu}/import', [MenuImportController::class, 'import'])->name('menus.import.store');
        Route::get('/coupons', [CouponController::class, 'index'])->name('coupons.index');
        Route::post('/coupons', [CouponController::class, 'store'])->name('coupons.store');
        Route::put('/coupons/{coupon}', [CouponController::class, 'update'])->name('coupons.update');
        Route::delete('/coupons/{coupon}', [CouponController::class, 'destroy'])->name('coupons.destroy');
        Route::get('/menus', [MenuController::class, 'index'])->name('menus.index');
        Route::post('/menus', [MenuController::class, 'store'])->name('menus.store');
        Route::get('/menus/{menu}', [MenuController::class, 'show'])->name('menus.show');
        Route::put('/menus/{menu}', [MenuController::class, 'update'])->name('menus.update');
Route::put('/menus/{menu}/theme', [MenuController::class, 'updateTheme'])->name('menus.theme.update');
        Route::delete('/menus/{menu}', [MenuController::class, 'destroy'])->name('menus.destroy');
        Route::post('/menus/{menu}/publish', [MenuController::class, 'publish'])->name('menus.publish');
        Route::post('/menus/{menu}/unpublish', [MenuController::class, 'unpublish'])->name('menus.unpublish');

        Route::post('/menus/{menu}/categories', [MenuCategoryController::class, 'store'])->name('menus.categories.store');
        Route::put('/menus/{menu}/categories/{category}', [MenuCategoryController::class, 'update'])->name('menus.categories.update');
        Route::delete('/menus/{menu}/categories/{category}', [MenuCategoryController::class, 'destroy'])->name('menus.categories.destroy');
        Route::post('/menus/{menu}/categories/reorder', [MenuCategoryController::class, 'reorder'])->name('menus.categories.reorder');

        Route::post('/menus/{menu}/categories/{category}/products', [ProductController::class, 'store'])->name('menus.products.store');
        Route::put('/menus/{menu}/categories/{category}/products/{product}', [ProductController::class, 'update'])->name('menus.products.update');
        Route::delete('/menus/{menu}/categories/{category}/products/{product}', [ProductController::class, 'destroy'])->name('menus.products.destroy');
        Route::post('/menus/{menu}/categories/{category}/products/reorder', [ProductController::class, 'reorder'])->name('menus.products.reorder');
        Route::post('/menus/{menu}/categories/{category}/products/{product}/image', [ProductImageController::class, 'store'])->name('menus.products.image.store');
        Route::delete('/menus/{menu}/categories/{category}/products/{product}/image', [ProductImageController::class, 'destroy'])->name('menus.products.image.destroy');

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

Route::get('/local', [LocalDirectoryController::class, 'index'])->name('local.directory');
Route::get('/local/{slug}', [LocalDirectoryController::class, 'show'])->name('local.business.show');

Route::middleware(['auth'])->prefix('local-scout')->name('local-scout.')->group(function () {
    Route::get('/', [LocalScoutController::class, 'index'])->name('index');
    Route::post('/discover', [LocalScoutController::class, 'discover'])->middleware('throttle:3,1')->name('discover');
    Route::post('/businesses', [LocalScoutController::class, 'store'])->middleware('throttle:20,1')->name('businesses.store');
    Route::patch('/businesses/{business}', [LocalScoutController::class, 'update'])->name('businesses.update');
    Route::post('/businesses/{business}/activities', [LocalScoutController::class, 'storeActivity'])->middleware('throttle:30,1')->name('businesses.activities.store');
});

// Public QR menu pages are intentionally outside auth and tenant middleware.
Route::get('/r/{restaurantSlug}/menu/{menuSlug}', [PublicMenuController::class, 'show'])
    ->name('public.menu.show');
Route::post('/r/{restaurantSlug}/menu/{menuSlug}/orders', [PublicOrderController::class, 'store'])
    ->middleware('throttle:10,1')->name('public.orders.store');
Route::post('/r/{restaurantSlug}/menu/{menuSlug}/ai-waiter', [AiWaiterController::class, 'chat'])
    ->middleware('throttle:30,1')->name('public.ai-waiter.chat');
Route::post('/r/{restaurantSlug}/menu/{menuSlug}/coupon-check', [PublicOrderController::class, 'checkCoupon'])
    ->middleware('throttle:20,1')->name('public.coupons.check');
Route::get('/r/{restaurantSlug}/orders/{orderNumber}/track/{token}', [OrderTrackingController::class, 'show'])
    ->middleware('throttle:30,1')->name('public.orders.track');
Route::post('/r/{restaurantSlug}/orders/{orderNumber}/track/{token}/review', [OrderTrackingController::class, 'storeReview'])
    ->middleware('throttle:5,1')->name('public.orders.review');
