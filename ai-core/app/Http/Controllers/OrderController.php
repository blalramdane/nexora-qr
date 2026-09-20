<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Modifier;
use App\Models\Order;
use App\Models\RestaurantTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function index(TenantContext $tenant): Response
    {
        $orders = Order::query()
            ->where('restaurant_id', $tenant->id())
            ->with(['table:id,name', 'items'])
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'customer_name' => $order->customer_name,
                'customer_phone' => $order->customer_phone,
                'table' => $order->table?->name,
                'items_count' => $order->items->sum('quantity'),
                'total' => $order->total,
                'created_at' => $order->created_at?->format('Y-m-d H:i'),
            ]);

        return Inertia::render('Orders/Index', [
            'restaurant' => $tenant->restaurant()->only(['id', 'name']),
            'orders' => $orders,
        ]);
    }

    public function store(Request $request): Response
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:50'],
            'items.*.modifier_ids' => ['nullable', 'array'],
            'items.*.modifier_ids.*' => ['integer'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
            'customer_name' => ['nullable', 'string', 'max:120'],
            'customer_phone' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'table_token' => ['nullable', 'string', 'max:120'],
            'restaurant_slug' => ['required', 'string', 'max:180'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
        ]);

        $restaurant = \App\Models\Restaurant::query()->where('slug', $data['restaurant_slug'])->where('is_active', true)->firstOrFail();
        $idempotency = $data['idempotency_key'] ?? null;

        if ($idempotency) {
            $existing = Order::query()
                ->where('restaurant_id', $restaurant->id)
                ->where('idempotency_key', $idempotency)
                ->first();

            if ($existing) {
                return Inertia::render('Orders/Confirmation', [
                    'order' => $this->confirmationPayload($existing),
                ]);
            }
        }

        $productIds = collect($data['items'])->pluck('product_id')->unique()->values();
        $products = $restaurant->categories()
            ->with(['products' => function ($query) use ($productIds) {
                $query->whereIn('id', $productIds)->where('is_available', true)->with('modifierGroups.modifiers');
            }])
            ->get()
            ->flatMap->products
            ->keyBy('id');

        if ($products->count() !== $productIds->count()) {
            abort(422, 'One or more products are unavailable.');
        }

        $table = null;
        if (!empty($data['table_token'])) {
            $table = RestaurantTable::query()
                ->where('restaurant_id', $restaurant->id)
                ->where('token', $data['table_token'])
                ->where('is_active', true)
                ->firstOrFail();
        }

        $branch = $table?->branch ?: $restaurant->branches()->where('is_active', true)->first();

        $order = DB::transaction(function () use ($data, $products, $restaurant, $table, $branch, $idempotency) {
            $subtotal = 0;

            $order = Order::create([
                'restaurant_id' => $restaurant->id,
                'branch_id' => $branch?->id,
                'table_id' => $table?->id,
                'order_number' => 'NXR-'.now()->format('Ymd').'-'.strtoupper(Str::random(6)),
                'status' => 'pending',
                'customer_name' => $data['customer_name'] ?? null,
                'customer_phone' => $data['customer_phone'] ?? null,
                'notes' => $data['notes'] ?? null,
                'subtotal' => 0,
                'total' => 0,
                'idempotency_key' => $idempotency,
            ]);

            foreach ($data['items'] as $item) {
                $product = $products->get((int) $item['product_id']);
                $modifierIds = collect($item['modifier_ids'] ?? [])->map(fn ($id) => (int) $id)->unique();
                $allowedModifiers = $product->modifierGroups->flatMap->modifiers->keyBy('id');
                $selectedModifiers = $modifierIds->map(fn ($id) => $allowedModifiers->get($id))->filter();

                if ($selectedModifiers->count() !== $modifierIds->count()) {
                    abort(422, 'Invalid modifier selection.');
                }

                foreach ($product->modifierGroups as $group) {
                    $count = $group->modifiers->whereIn('id', $modifierIds->all())->count();
                    if ($group->is_required && $count < max(1, $group->min_selections)) {
                        abort(422, "Required modifier group {$group->name} is incomplete.");
                    }
                    if ($count < $group->min_selections || $count > $group->max_selections) {
                        abort(422, "Invalid modifier count for {$group->name}.");
                    }
                }

                $modifierTotal = $selectedModifiers->sum(fn ($modifier) => (float) $modifier->price_delta);
                $unitPrice = (float) $product->price + $modifierTotal;
                $lineTotal = $unitPrice * (int) $item['quantity'];
                $subtotal += $lineTotal;

                $orderItem = $order->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'unit_price' => $unitPrice,
                    'quantity' => $item['quantity'],
                    'line_total' => $lineTotal,
                    'notes' => $item['notes'] ?? null,
                ]);

                foreach ($selectedModifiers as $modifier) {
                    $orderItem->modifiers()->create([
                        'modifier_id' => $modifier->id,
                        'modifier_name' => $modifier->name,
                        'price_delta' => $modifier->price_delta,
                    ]);
                }
            }

            $order->update(['subtotal' => $subtotal, 'total' => $subtotal]);
            $order->events()->create(['event' => 'created', 'to_status' => 'pending']);

            return $order;
        });

        return Inertia::render('Orders/Confirmation', [
            'order' => $this->confirmationPayload($order->fresh()),
        ]);
    }

    public function updateStatus(Request $request, Order $order, TenantContext $tenant): RedirectResponse
    {
        abort_unless($order->restaurant_id === $tenant->id(), 404);

        $data = $request->validate(['status' => ['required', 'in:pending,confirmed,preparing,ready,completed,cancelled']]);

        $from = $order->status;
        $order->update(['status' => $data['status']]);
        $order->events()->create([
            'event' => 'status_changed',
            'from_status' => $from,
            'to_status' => $data['status'],
            'user_id' => $request->user()?->id,
        ]);

        return back();
    }

    private function confirmationPayload(Order $order): array
    {
        $order->loadMissing('items.modifiers', 'table');

        return [
            'order_number' => $order->order_number,
            'status' => $order->status,
            'total' => $order->total,
            'table' => $order->table?->name,
            'items' => $order->items->map(fn ($item) => [
                'name' => $item->product_name,
                'quantity' => $item->quantity,
                'line_total' => $item->line_total,
                'modifiers' => $item->modifiers->pluck('modifier_name')->values(),
            ]),
        ];
    }
}