<?php

namespace App\Http\Controllers;

use App\Models\DeliveryCashSettlement;
use App\Models\DeliveryRider;
use App\Models\DeliveryRiderLedgerEntry;
use App\Models\Order;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryCashSettlementController extends Controller
{
    public function index(): Response
    {
        $this->authorizeManagement();
        $restaurantId = app(TenantContext::class)->id();
        $eligibleOrders = Order::query()->where('restaurant_id', $restaurantId)->where('fulfillment_type', 'delivery')
            ->where('delivery_status', 'delivered')->whereIn('payment_method', ['cash_on_delivery', 'cash'])->where('payment_status', 'unpaid')
            ->whereDoesntHave('cashSettlements')->with('deliveryRider.user:id,name')->oldest('delivered_at')->get();
        $settlements = DeliveryCashSettlement::query()->where('restaurant_id', $restaurantId)
            ->with(['rider.user:id,name', 'orders:id,order_number,customer_name']) ->latest()->limit(100)->get()->map(fn ($s) => [
                'id' => $s->id, 'rider_name' => $s->rider?->user?->name ?? 'مندوب غير متاح',
                'expected_amount' => (float) $s->expected_amount, 'collected_amount' => (float) $s->collected_amount,
                'handed_over_amount' => (float) $s->handed_over_amount,
                'collection_difference' => round((float) $s->expected_amount - (float) $s->collected_amount, 2),
                'handover_difference' => round((float) $s->collected_amount - (float) $s->handed_over_amount, 2),
                'status' => $s->status, 'notes' => $s->notes, 'created_at' => $s->created_at?->toISOString(),
                'orders' => $s->orders->map(fn ($o) => ['order_number' => $o->order_number, 'customer_name' => $o->customer_name, 'expected_amount' => (float) $o->pivot->expected_amount])->values(),
            ])->values();
        return Inertia::render('Delivery/Settlements', [
            'riders' => DeliveryRider::query()->where('status', 'active')->whereHas('restaurants', fn ($q) => $q->where('restaurants.id', $restaurantId)->wherePivot('status', 'active'))->with('user:id,name')->get()->map(fn ($r) => ['id' => $r->id, 'name' => $r->user->name])->values(),
            'eligibleOrders' => $eligibleOrders->map(fn ($o) => ['id' => $o->id, 'order_number' => $o->order_number, 'customer_name' => $o->customer_name, 'total' => (float) $o->total, 'delivered_at' => $o->delivered_at?->toISOString(), 'delivery_rider_id' => $o->delivery_rider_id, 'rider_name' => $o->deliveryRider?->user?->name ?? 'مندوب غير متاح'])->values(),
            'settlements' => $settlements,
            'summary' => [
                'eligible_orders' => $eligibleOrders->count(), 'eligible_amount' => (float) $eligibleOrders->sum(fn ($o) => (float) $o->total),
                'pending_handover' => (float) DeliveryCashSettlement::query()->where('restaurant_id', $restaurantId)->sum(DB::raw('collected_amount - handed_over_amount')),
                'collection_shortfall' => (float) DeliveryCashSettlement::query()->where('restaurant_id', $restaurantId)->sum(DB::raw('expected_amount - collected_amount')),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManagement();
        $restaurantId = app(TenantContext::class)->id();
        $data = $request->validate([
            'delivery_rider_id' => ['required', 'integer'], 'order_ids' => ['required', 'array', 'min:1', 'max:100'],
            'order_ids.*' => ['required', 'integer', 'distinct'], 'collected_amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'handed_over_amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'], 'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        abort_unless((float) $data['handed_over_amount'] <= (float) $data['collected_amount'], 422, 'المبلغ المسلّم للمطعم لا يمكن أن يتجاوز المبلغ المحصّل.');
        DB::transaction(function () use ($data, $restaurantId, $request) {
            $rider = DeliveryRider::query()->where('status', 'active')->findOrFail($data['delivery_rider_id']);
            $isAssignedToRestaurant = DB::table('restaurant_delivery_riders')->where('restaurant_id', $restaurantId)
                ->where('delivery_rider_id', $rider->id)->where('status', 'active')->exists();
            abort_unless($isAssignedToRestaurant, 404);
            $orders = Order::query()->where('restaurant_id', $restaurantId)->where('delivery_rider_id', $rider->id)->where('fulfillment_type', 'delivery')
                ->where('delivery_status', 'delivered')->whereIn('payment_method', ['cash_on_delivery', 'cash'])->where('payment_status', 'unpaid')
                ->whereDoesntHave('cashSettlements')->whereIn('id', $data['order_ids'])->lockForUpdate()->get();
            abort_unless($orders->count() === count($data['order_ids']), 422, 'فيه طلب غير مؤهل أو اتسوّى قبل كده. حدّث الصفحة وراجع الطلبات.');
            $settlement = DeliveryCashSettlement::create([
                'restaurant_id' => $restaurantId, 'delivery_rider_id' => $rider->id, 'created_by' => $request->user()->id,
                'confirmed_by' => $request->user()->id, 'expected_amount' => round((float) $orders->sum(fn ($o) => (float) $o->total), 2),
                'collected_amount' => $data['collected_amount'], 'handed_over_amount' => $data['handed_over_amount'],
                'status' => 'confirmed', 'notes' => $data['notes'] ?? null, 'confirmed_at' => now(),
            ]);
            DeliveryRiderLedgerEntry::create([
                'restaurant_id' => $restaurantId, 'delivery_rider_id' => $rider->id, 'created_by' => $request->user()->id,
                'settlement_id' => $settlement->id, 'type' => 'collection', 'amount' => $data['collected_amount'],
                'description' => 'تحصيل نقدي مسجل ضمن التسوية #' . $settlement->id, 'occurred_at' => now(),
            ]);
            if ((float) $data['handed_over_amount'] > 0) {
                DeliveryRiderLedgerEntry::create([
                    'restaurant_id' => $restaurantId, 'delivery_rider_id' => $rider->id, 'created_by' => $request->user()->id,
                    'settlement_id' => $settlement->id, 'type' => 'handover', 'amount' => $data['handed_over_amount'],
                    'description' => 'تسليم نقدية للمطعم ضمن التسوية #' . $settlement->id, 'occurred_at' => now(),
                ]);
            }
            foreach ($orders as $order) {
                $settlement->orders()->attach($order->id, ['expected_amount' => $order->total]);
                if (round((float) $data['collected_amount'], 2) === round((float) $settlement->expected_amount, 2)) {
                    $order->update(['payment_status' => 'paid']);
                }
            }
        });
        return to_route('delivery.settlements.index')->with('success', 'تم حفظ تسوية المندوب وربط الطلبات بها. راجع أي فرق تحصيل أو تسليم ظاهر في التقرير.');
    }

    private function authorizeManagement(): void
    {
        $user = request()->user(); $restaurant = app(TenantContext::class)->restaurant();
        abort_unless($user?->isOwnerOf($restaurant) || $user?->roleIn($restaurant) === 'manager', 403);
    }
}
