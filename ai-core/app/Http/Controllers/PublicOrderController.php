<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Coupon;
use App\Models\Restaurant;
use App\Services\SyncRestaurantToLocalDirectory;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class PublicOrderController extends Controller
{
    public function checkCoupon(Request $request, string $restaurantSlug, string $menuSlug): JsonResponse
    {
        $data = $request->validate(['coupon_code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/'], 'subtotal' => ['required', 'numeric', 'min:0', 'max:1000000']]);
        $restaurant = Restaurant::query()->where('slug', $restaurantSlug)->where('is_active', true)->firstOrFail();
        app(TenantContext::class)->set($restaurant);
        Menu::query()->where('restaurant_id', $restaurant->id)->where('slug', $menuSlug)->where('is_published', true)->firstOrFail();
        $coupon = Coupon::query()->where('restaurant_id', $restaurant->id)->where('code', Str::upper(trim($data['coupon_code'])))->first();
        abort_unless($coupon && $coupon->isUsableNow(), 422, 'كود الخصم غير صالح أو انتهت صلاحيته.');
        abort_unless((float) $data['subtotal'] >= (float) $coupon->minimum_subtotal, 422, 'قيمة المنتجات أقل من الحد الأدنى لاستخدام الكوبون.');

        return response()->json(['code' => $coupon->code, 'discount_amount' => $coupon->discountFor((float) $data['subtotal']), 'message' => 'تم تطبيق كود الخصم.']);
    }

    public function store(Request $request, string $restaurantSlug, string $menuSlug): JsonResponse
    {
        $idempotencyKey = trim((string) $request->header('Idempotency-Key', ''));
        if ($idempotencyKey !== '') {
            validator(['key' => $idempotencyKey], ['key' => ['required', 'string', 'min:8', 'max:100', 'regex:/^[A-Za-z0-9._:-]+$/']])->validate();
        }
        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'fulfillment_type' => ['required', 'in:pickup,delivery'],
            'delivery_address' => ['nullable', 'string', 'max:1000'],
            'delivery_area' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'coupon_code' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            // A cart may legitimately contain the same product more than once
            // when the customer chooses different sizes or modifier sets.
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:30'],
            'items.*.variant_id' => ['nullable', 'integer'],
            'items.*.modifier_ids' => ['sometimes', 'array', 'max:20'],
            'items.*.modifier_ids.*' => ['integer'],
        ]);

        $restaurant = Restaurant::query()->where('slug', $restaurantSlug)->where('is_active', true)->firstOrFail();
        abort_if($data['fulfillment_type'] === 'delivery' && !$restaurant->delivery_enabled, 422, 'التوصيل غير متاح حاليًا.');
        abort_if($data['fulfillment_type'] === 'pickup' && !$restaurant->pickup_enabled, 422, 'الاستلام من المطعم غير متاح حاليًا.');
        if ($data['fulfillment_type'] === 'delivery' && empty(trim($data['delivery_address'] ?? ''))) {
            throw \Illuminate\Validation\ValidationException::withMessages(['delivery_address' => 'اكتب عنوان التوصيل.']);
        }
        if ($data['fulfillment_type'] === 'delivery' && count($restaurant->delivery_areas ?? []) > 0) {
            abort_unless(in_array($data['delivery_area'] ?? '', $restaurant->delivery_areas, true), 422, 'اختار منطقة من مناطق التوصيل المتاحة للمطعم.');
        }
        app(TenantContext::class)->set($restaurant);
        $menu = Menu::query()->where('restaurant_id', $restaurant->id)->where('slug', $menuSlug)->where('is_published', true)->firstOrFail();

        $requestHash = hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        if ($idempotencyKey !== '') {
            $existing = Order::query()->where('restaurant_id', $restaurant->id)->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                abort_unless(hash_equals((string) $existing->idempotency_request_hash, $requestHash), 409, 'مفتاح الطلب مستخدم مع بيانات مختلفة. استخدم مفتاحًا جديدًا للطلب الجديد.');
                $trackingToken = $existing->tracking_token_encrypted ? Crypt::decryptString($existing->tracking_token_encrypted) : null;
                return $this->orderResponse($restaurant, $existing, $trackingToken, true);
            }
        }

        $order = DB::transaction(function () use ($data, $restaurant, $menu, $idempotencyKey, $requestHash) {
            $lines = [];
            $subtotal = 0;
            $itemsCount = 0;

            foreach ($data['items'] as $requested) {
                $product = \App\Models\Product::query()
                    ->where('id', (int) $requested['product_id'])
                    ->where('restaurant_id', $restaurant->id)
                    ->where('is_available', true)
                    ->whereHas('category', fn ($query) => $query->where('menu_id', $menu->id)->where('is_active', true))
                    ->first();

                abort_unless($product && (int) $product->restaurant_id === (int) $restaurant->id, 422, 'أحد المنتجات المختارة لم يعد متاحًا. حدّث المنيو وحاول مرة أخرى.');

                $unitPrice = (float) $product->price;
                $variantName = null;
                if (!empty($requested['variant_id'])) {
                    $variant = $product->variants()->where('is_active', true)->findOrFail($requested['variant_id']);
                    $unitPrice = $variant->price !== null ? (float) $variant->price : $unitPrice + (float) $variant->price_delta;
                    $variantName = $variant->name;
                }

                $modifierIds = array_map('intval', $requested['modifier_ids'] ?? []);
                abort_unless(count($modifierIds) === count(array_unique($modifierIds)), 422, 'تم اختيار إحدى الإضافات أكثر من مرة.');
                $availableModifiers = $product->modifiers()->where('is_active', true)->get();
                $modifiers = $availableModifiers->whereIn('id', $modifierIds)->values();
                abort_unless($modifiers->count() === count(array_unique($modifierIds)), 422, 'إحدى الإضافات المختارة لم تعد متاحة.');
                $missingRequiredModifier = $availableModifiers->contains(fn ($modifier) => $modifier->is_required && !in_array((int) $modifier->id, $modifierIds, true));
                abort_unless(!$missingRequiredModifier, 422, 'من فضلك اختار كل الإضافات المطلوبة للمنتج.');
                $groupedModifiers = $availableModifiers->whereNotNull('group_name')->groupBy('group_name');
                foreach ($groupedModifiers as $groupName => $groupOptions) {
                    $selectedCount = $groupOptions->whereIn('id', $modifierIds)->count();
                    $minimum = (int) $groupOptions->max('group_min_choices');
                    $maximum = max(1, (int) $groupOptions->min('group_max_choices'));
                    abort_unless($selectedCount >= $minimum && $selectedCount <= $maximum, 422, "اختيارات مجموعة {$groupName} غير صالحة؛ اختار من {$minimum} إلى {$maximum}.");
                }
                $modifierSnapshots = $modifiers->map(fn ($modifier) => [
                    'id' => $modifier->id,
                    'name' => $modifier->name,
                    'price' => (float) $modifier->price_delta,
                ])->values()->all();
                $unitPrice += array_sum(array_column($modifierSnapshots, 'price'));

                $quantity = (int) $requested['quantity'];
                $lineTotal = round($unitPrice * $quantity, 2);
                $subtotal += $lineTotal;
                $itemsCount += $quantity;
                $lines[] = ['product_id' => $product->id, 'product_name' => $product->name, 'variant_name' => $variantName, 'modifiers' => $modifierSnapshots, 'quantity' => $quantity, 'unit_price' => $unitPrice, 'line_total' => $lineTotal];
            }

            $coupon = null;
            $discountAmount = 0.0;
            if (!empty($data['coupon_code'])) {
                $coupon = Coupon::query()->where('restaurant_id', $restaurant->id)
                    ->where('code', Str::upper(trim($data['coupon_code'])))
                    ->lockForUpdate()->first();
                abort_unless($coupon && $coupon->isUsableNow(), 422, 'كود الخصم غير صالح أو انتهت صلاحيته.');
                abort_unless($subtotal >= (float) $coupon->minimum_subtotal, 422, 'قيمة المنتجات أقل من الحد الأدنى لاستخدام الكوبون.');
                $discountAmount = $coupon->discountFor((float) $subtotal);
                $coupon->increment('usage_count');
            }

            $deliveryFee = $data['fulfillment_type'] === 'delivery' ? (float) $restaurant->delivery_fee : 0.0;
            $trackingToken = Str::random(64);
            $order = Order::create([
                'restaurant_id' => $restaurant->id,
                'menu_id' => $menu->id,
                'source' => 'qr_menu',
                'payment_method' => 'cash_on_delivery',
                'payment_status' => 'unpaid',
                'order_number' => 'NX-' . now()->format('ymd') . '-' . Str::upper(Str::random(6)),
                'tracking_token_hash' => hash('sha256', $trackingToken),
                'tracking_token_encrypted' => $idempotencyKey !== '' ? Crypt::encryptString($trackingToken) : null,
                'idempotency_key' => $idempotencyKey !== '' ? $idempotencyKey : null,
                'idempotency_request_hash' => $idempotencyKey !== '' ? $requestHash : null,
                'customer_name' => $data['customer_name'],
                'customer_phone' => $data['customer_phone'],
                'fulfillment_type' => $data['fulfillment_type'],
                'delivery_address' => $data['fulfillment_type'] === 'delivery' ? trim($data['delivery_address']) : null,
                'delivery_area' => $data['fulfillment_type'] === 'delivery' ? ($data['delivery_area'] ?? null) : null,
                'notes' => $data['notes'] ?? null,
                'status' => 'new',
                'items_count' => $itemsCount,
                'subtotal' => $subtotal,
                'delivery_fee' => $deliveryFee,
                'coupon_id' => $coupon?->id,
                'coupon_code' => $coupon?->code,
                'discount_amount' => $discountAmount,
                'total' => round(max(0, $subtotal + $deliveryFee - $discountAmount), 2),
            ]);
            $order->items()->createMany($lines);
            $order->statusHistory()->create(['from_status' => null, 'to_status' => 'new']);
            $order->integrationEvents()->create([
                'restaurant_id' => $restaurant->id,
                'event_type' => 'order.created',
                'status' => 'pending',
                'payload' => ['order_id' => $order->id, 'order_number' => $order->order_number, 'source' => 'qr_menu', 'status' => 'new', 'total' => (float) $order->total],
            ]);
            return [$order, $trackingToken];
        });

        [$order, $trackingToken] = $order;
        $business = app(SyncRestaurantToLocalDirectory::class)->sync($restaurant);
        $business->activities()->create([
            'type' => 'order_created',
            'outcome' => 'received',
            'channel' => 'qr_menu',
            'notes' => 'تم استلام طلب جديد من المنيو: '.$order->order_number,
            'occurred_at' => $order->created_at ?? now(),
        ]);
        return $this->orderResponse($restaurant, $order, $trackingToken, false);
    }

    private function orderResponse(Restaurant $restaurant, Order $order, ?string $trackingToken, bool $replayed): JsonResponse
    {
        $trackingUrl = route('public.orders.track', [$restaurant->slug, $order->order_number, $trackingToken]);

        return response()->json(['message' => $replayed ? 'الطلب مسجل بالفعل؛ تم إرجاع نفس النتيجة دون إنشاء طلب جديد.' : 'تم إرسال طلبك للمطعم بنجاح.', 'replayed' => $replayed, 'order' => ['number' => $order->order_number, 'status' => $order->status, 'subtotal' => $order->subtotal, 'discount_amount' => $order->discount_amount, 'coupon_code' => $order->coupon_code, 'total' => $order->total, 'delivery_fee' => $order->delivery_fee, 'tracking_url' => $trackingUrl]], $replayed ? 200 : 201);
    }
}
