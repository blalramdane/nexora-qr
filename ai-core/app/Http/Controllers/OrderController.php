<?php

namespace App\Http\Controllers;

use App\Concerns\LogsActivity;
use App\Models\Menu;
use App\Models\Order;
use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    use LogsActivity;

    public function kds(): JsonResponse
    {
        $this->authorizeOrderManagement();
        $restaurantId = app(TenantContext::class)->id();
        $orders = Order::query()->where('restaurant_id', $restaurantId)
            ->whereIn('status', ['new', 'confirmed', 'preparing', 'ready'])
            ->with(['items', 'restaurantTable'])
            ->latest()->limit(50)->get()
            ->map(fn (Order $o) => [
                'id' => $o->id,
                'order_number' => $o->order_number,
                'status' => $o->status,
                'items_count' => $o->items_count,
                'fulfillment_type' => $o->fulfillment_type,
                'created_at' => $o->created_at?->toIso8601String(),
                'customer_name' => $o->customer_name,
                'restaurant_table_name' => $o->restaurant_table_name ?? $o->restaurantTable?->name,
                'notes' => $o->notes,
                'items' => $o->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_name' => $item->product_name,
                    'quantity' => (int) $item->quantity,
                    'variant_name' => $item->variant_name,
                    'modifiers' => collect($item->modifiers ?? [])->pluck('name')->filter()->values(),
                ])->values(),
            ]);

        return response()->json($orders);
    }

    public function kdsPage(): Response
    {
        $this->authorizeOrderManagement();
        $restaurantId = app(TenantContext::class)->id();

        return Inertia::render('Dashboard/KDS', [
            'ordersBaseUrl' => request()->routeIs('owner.restaurants.kds')
                ? '/owner/restaurants/'.$restaurantId.'/orders'
                : '/orders',
        ]);
    }

    public function index(): Response
    {
        $this->authorizeOrderManagement();
        $restaurantId = app(TenantContext::class)->id();
        $orders = Order::query()->where('restaurant_id', $restaurantId)->with(['menu:id,name', 'items', 'deliveryRider.user:id,name', 'restaurantTable:id,name'])
            ->latest()->limit(100)->get();

        $restaurant = app(TenantContext::class)->restaurant();
        $ownerContext = request()->routeIs('owner.restaurants.orders.*');
        $ordersBaseUrl = $ownerContext ? '/owner/restaurants/'.$restaurant->id.'/orders' : '/orders';
        $kdsUrl = $ownerContext ? '/owner/restaurants/'.$restaurant->id.'/kds' : '/kds';
        $settingsUrl = $ownerContext
            ? route('owner.restaurants.settings.orders.edit', $restaurant)
            : route('restaurant.settings.edit');
        $manualOrderCatalog = Menu::query()->where('restaurant_id', $restaurantId)->where('is_published', true)
            ->with(['categories.products' => fn ($query) => $query->where('is_available', true)->select('id', 'menu_category_id', 'name', 'price')])
            ->get(['id', 'name'])
            ->map(fn ($menu) => [
                'id' => $menu->id,
                'name' => $menu->name,
                'products' => $menu->categories->flatMap(fn ($category) => $category->products)->values()->map(fn ($product) => [
                    'id' => $product->id, 'name' => $product->name, 'price' => (float) $product->price,
                ]),
            ])->values();

        return Inertia::render('Orders/Index', [
            'orders' => $orders,
            'ordersBaseUrl' => $ordersBaseUrl,
            'kdsUrl' => $kdsUrl,
            'settingsUrl' => $settingsUrl,
            'backUrl' => $ownerContext ? route('owner.menus.index') : route('dashboard'),
            'ownerContext' => $ownerContext,
            'dashboardOrderNotificationsEnabled' => (bool) ($restaurant->dashboard_order_notifications_enabled ?? true),
            'manualOrderCatalog' => $manualOrderCatalog,
            'salesSummary' => [
                'completed_count' => Order::query()->where('restaurant_id', $restaurantId)->where('status', 'completed')->count(),
                'completed_revenue' => (float) Order::query()->where('restaurant_id', $restaurantId)->where('status', 'completed')->sum('total'),
                'completed_discounts' => (float) Order::query()->where('restaurant_id', $restaurantId)->where('status', 'completed')->sum('discount_amount'),
                'today_revenue' => (float) Order::query()->where('restaurant_id', $restaurantId)->where('status', 'completed')->whereDate('created_at', now()->toDateString())->sum('total'),
            ],
            'sourceSummary' => Order::query()->where('restaurant_id', $restaurantId)
                ->selectRaw('source, COUNT(*) as order_count, SUM(CASE WHEN status = ? THEN total ELSE 0 END) as completed_revenue', ['completed'])
                ->groupBy('source')->orderByDesc('order_count')->get()
                ->map(fn ($row) => ['source' => $row->source, 'order_count' => (int) $row->order_count, 'completed_revenue' => (float) $row->completed_revenue])->values(),
            'restaurantContact' => [
                'name' => $restaurant->name,
                'whatsappPhone' => $restaurant->whatsapp_phone,
            ],
            'deliveryRiders' => $restaurant->deliveryRiders()->wherePivot('status', 'active')->where('delivery_riders.status', 'active')->with('user:id,name')->get()->map(fn ($rider) => ['id' => $rider->id, 'name' => $rider->user->name, 'phone' => $rider->phone])->values(),
        ]);
    }

    public function storeManual(Request $request): RedirectResponse
    {
        $this->authorizeOrderManagement();
        $restaurant = app(TenantContext::class)->restaurant();
        $data = $request->validate([
            'menu_id' => ['required', 'integer', 'exists:menus,id'],
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+() .-]{7,30}$/'],
            'source' => ['required', 'in:manual,whatsapp,phone,facebook,instagram,tiktok,external_app'],
            'fulfillment_type' => ['required', 'in:pickup,delivery'],
            'delivery_address' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['required', 'in:cash_on_delivery,cash,card,wallet,bank_transfer'],
            'payment_status' => ['required', 'in:unpaid,paid,refunded'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        abort_unless(Menu::query()->where('restaurant_id', $restaurant->id)->whereKey($data['menu_id'])->where('is_published', true)->exists(), 422, 'اختار منيو منشورة تابعة للمطعم.');
        if ($data['fulfillment_type'] === 'delivery') {
            abort_unless($restaurant->delivery_enabled, 422, 'التوصيل غير مفعل للمطعم.');
            validator($data, ['delivery_address' => ['required', 'string', 'max:1000']])->validate();
        }
        if ($data['fulfillment_type'] === 'pickup') {
            abort_unless($restaurant->pickup_enabled, 422, 'الاستلام من المطعم غير متاح حاليًا.');
        }

        $products = Product::query()->where('restaurant_id', $restaurant->id)->where('is_available', true)
            ->whereHas('category', fn ($query) => $query->where('menu_id', $data['menu_id']))
            ->whereIn('id', collect($data['items'])->pluck('product_id'))->get()->keyBy('id');
        abort_unless($products->count() === collect($data['items'])->pluck('product_id')->unique()->count(), 422, 'يوجد منتج غير متاح أو لا يتبع المنيو المختارة.');

        DB::transaction(function () use ($data, $products, $restaurant, $request) {
            $subtotal = 0;
            $itemRows = [];
            foreach ($data['items'] as $item) {
                $product = $products->get((int) $item['product_id']);
                $quantity = (int) $item['quantity'];
                $unitPrice = (float) $product->price;
                $lineTotal = round($unitPrice * $quantity, 2);
                $subtotal += $lineTotal;
                $itemRows[] = ['product_id' => $product->id, 'product_name' => $product->name, 'quantity' => $quantity, 'unit_price' => $unitPrice, 'line_total' => $lineTotal];
            }
            $deliveryFee = $data['fulfillment_type'] === 'delivery' ? (float) $restaurant->delivery_fee : 0;
            $order = Order::create([
                'restaurant_id' => $restaurant->id, 'menu_id' => $data['menu_id'],
                'order_number' => 'NX-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
                'customer_name' => $data['customer_name'], 'customer_phone' => $data['customer_phone'],
                'source' => $data['source'], 'source_reference' => 'staff-entry',
                'fulfillment_type' => $data['fulfillment_type'], 'delivery_address' => $data['fulfillment_type'] === 'delivery' ? $data['delivery_address'] : null,
                'payment_method' => $data['payment_method'], 'payment_status' => $data['payment_status'],
                'notes' => $data['notes'] ?? null, 'status' => 'new', 'items_count' => array_sum(array_column($itemRows, 'quantity')),
                'subtotal' => $subtotal, 'delivery_fee' => $deliveryFee, 'discount_amount' => 0, 'total' => max(0, $subtotal + $deliveryFee),
            ]);
            $order->items()->createMany($itemRows);
            $order->statusHistory()->create(['from_status' => null, 'to_status' => 'new', 'changed_by' => $request->user()?->id]);
        });

        return to_route(request()->routeIs('owner.restaurants.orders.*') ? 'owner.restaurants.orders.index' : 'orders.index', request()->routeIs('owner.restaurants.orders.*') ? ['restaurant' => $restaurant->id] : [])->with('success', 'تم تسجيل الطلب وإضافته إلى صندوق الطلبات.');
    }

    public function updateStatus(Request $request, int $order): RedirectResponse
    {
        $this->authorizeOrderManagement();
        $data = $request->validate(['status' => ['required', 'in:confirmed,preparing,ready,completed,cancelled']]);
        $transitions = [
            'new' => ['confirmed', 'cancelled'],
            'confirmed' => ['preparing', 'cancelled'],
            'preparing' => ['ready', 'cancelled'],
            'ready' => ['completed', 'cancelled'],
            'completed' => [],
            'cancelled' => [],
        ];
        DB::transaction(function () use ($data, $order, $transitions, $request) {
            $record = Order::query()->where('restaurant_id', app(TenantContext::class)->id())->lockForUpdate()->findOrFail($order);
            abort_unless(in_array($data['status'], $transitions[$record->status] ?? [], true), 422, 'انتقال حالة الطلب غير مسموح.');
            $previousStatus = $record->status;
            $record->update(['status' => $data['status']]);
            $record->statusHistory()->create([
                'from_status' => $previousStatus,
                'to_status' => $data['status'],
                'changed_by' => $request->user()?->id,
            ]);

        });

        $this->logActivity('order.status_changed', Order::class, $order, ['new_status' => $data['status']]);

        return request()->routeIs('owner.restaurants.orders.*')
            ? to_route('owner.restaurants.orders.index', ['restaurant' => app(TenantContext::class)->id()])->with('success', 'تم تحديث حالة الطلب.')
            : to_route('orders.index')->with('success', 'تم تحديث حالة الطلب.');
    }

    public function updatePaymentStatus(Request $request, int $order): RedirectResponse
    {
        $this->authorizeOrderManagement();
        $data = $request->validate(['payment_status' => ['required', 'in:unpaid,paid,refunded']]);
        DB::transaction(function () use ($data, $order) {
            $record = Order::query()->where('restaurant_id', app(TenantContext::class)->id())->lockForUpdate()->findOrFail($order);
            $allowed = [
                'unpaid' => ['paid'],
                'paid' => ['refunded'],
                'refunded' => [],
            ];
            abort_unless(in_array($data['payment_status'], $allowed[$record->payment_status] ?? [], true), 422, 'تغيير حالة الدفع غير مسموح.');
            abort_unless($record->status !== 'cancelled' || $data['payment_status'] === 'refunded', 422, 'لا يمكن تسجيل دفع لطلب ملغي.');
            $record->update(['payment_status' => $data['payment_status']]);
        });

        return request()->routeIs('owner.restaurants.orders.*')
            ? to_route('owner.restaurants.orders.index', ['restaurant' => app(TenantContext::class)->id()])->with('success', 'تم تحديث حالة الدفع.')
            : to_route('orders.index')->with('success', 'تم تحديث حالة الدفع.');
    }

    private function authorizeOrderManagement(): void
    {
        $user = request()->user();
        $restaurant = app(TenantContext::class)->restaurant();
        abort_unless($user?->isPlatformOwner() || $user?->isOwnerOf($restaurant) || $user?->roleIn($restaurant) === 'manager', 403);
    }
}
