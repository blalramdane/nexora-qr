<?php

namespace App\Http\Controllers;

use App\Models\DeliveryTrip;
use App\Models\Order;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryTripController extends Controller
{
    public function index(): Response
    {
        $restaurant = app(TenantContext::class)->restaurant();
        $this->authorizeManagement();
        $riders = $restaurant->deliveryRiders()->wherePivot('status', 'active')->where('delivery_riders.status', 'active')->with('user:id,name')->get()
            ->map(fn ($rider) => ['id' => $rider->id, 'name' => $rider->user->name, 'phone' => $rider->phone]);
        $orders = Order::query()->where('restaurant_id', $restaurant->id)->where('fulfillment_type', 'delivery')
            ->where('delivery_status', 'assigned')->whereNotNull('delivery_rider_id')
            ->whereNotIn('status', ['cancelled', 'completed'])
            ->whereDoesntHave('deliveryTrips')
            ->with('deliveryRider.user:id,name')->orderBy('delivery_area')->latest()->limit(200)->get()
            ->map(fn ($order) => ['id' => $order->id, 'order_number' => $order->order_number, 'customer_name' => $order->customer_name,
                'delivery_area' => $order->delivery_area, 'delivery_address' => $order->delivery_address, 'total' => $order->total,
                'delivery_rider_id' => $order->delivery_rider_id, 'rider_name' => $order->deliveryRider?->user?->name]);
        $trips = DeliveryTrip::query()->where('restaurant_id', $restaurant->id)
            ->with(['rider.user:id,name', 'orders' => fn ($query) => $query->with('deliveryEvents:id,order_id,event_type,created_at')->withPivot('stop_number')])
            ->latest()->limit(100)->get()->map(fn ($trip) => [
                'id' => $trip->id, 'trip_number' => $trip->trip_number, 'status' => $trip->status, 'notes' => $trip->notes,
                'started_at' => $trip->started_at?->toIso8601String(), 'completed_at' => $trip->completed_at?->toIso8601String(),
                'rider' => ['id' => $trip->rider->id, 'name' => $trip->rider->user->name],
                'orders' => $trip->orders->map(fn ($order) => ['id' => $order->id, 'order_number' => $order->order_number,
                    'customer_name' => $order->customer_name, 'delivery_area' => $order->delivery_area, 'delivery_address' => $order->delivery_address,
                    'delivery_status' => $order->delivery_status, 'stop_number' => $order->pivot->stop_number])->values(),
            ]);

        return Inertia::render('Delivery/Trips', ['restaurant' => ['name' => $restaurant->name], 'riders' => $riders, 'eligibleOrders' => $orders, 'trips' => $trips]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManagement();
        $restaurant = app(TenantContext::class)->restaurant();
        $data = $request->validate([
            'delivery_rider_id' => ['required', 'integer', Rule::exists('delivery_riders', 'id')->where('status', 'active')],
            'order_ids' => ['required', 'array', 'min:2', 'max:30'],
            'order_ids.*' => ['required', 'integer', 'distinct'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        abort_unless($restaurant->deliveryRiders()->whereKey($data['delivery_rider_id'])->wherePivot('status', 'active')->where('delivery_riders.status', 'active')->exists(), 422, 'المندوب غير معتمد لهذا المطعم.');

        DB::transaction(function () use ($data, $restaurant, $request) {
            $orders = Order::query()->where('restaurant_id', $restaurant->id)->whereIn('id', $data['order_ids'])->lockForUpdate()->get();
            abort_unless($orders->count() === count($data['order_ids']), 422, 'فيه طلب غير موجود أو لا يتبع هذا المطعم.');
            foreach ($orders as $order) {
                abort_unless($order->fulfillment_type === 'delivery' && (int) $order->delivery_rider_id === (int) $data['delivery_rider_id']
                    && $order->delivery_status === 'assigned' && !in_array($order->status, ['cancelled', 'completed'], true), 422,
                    'كل الطلبات لازم تكون توصيل ومسندة لنفس المندوب ولم تبدأ بعد.');
                abort_if(DB::table('delivery_trip_orders')->where('order_id', $order->id)->exists(), 422, 'أحد الطلبات موجود بالفعل في رحلة أخرى.');
            }
            $trip = DeliveryTrip::create(['restaurant_id' => $restaurant->id, 'delivery_rider_id' => $data['delivery_rider_id'],
                'trip_number' => 'TR-' . now()->format('ymdHis') . '-' . random_int(100, 999), 'status' => 'planned',
                'notes' => $data['notes'] ?? null, 'created_by' => $request->user()->id]);
            foreach (array_values($data['order_ids']) as $index => $orderId) {
                $trip->orders()->attach($orderId, ['stop_number' => $index + 1]);
                Order::query()->whereKey($orderId)->firstOrFail()->deliveryEvents()->create([
                    'restaurant_id' => $restaurant->id, 'delivery_rider_id' => $data['delivery_rider_id'], 'actor_id' => $request->user()->id,
                    'event_type' => 'trip_planned', 'from_status' => 'assigned', 'to_status' => 'assigned',
                    'notes' => 'إضافة الطلب إلى رحلة ' . $trip->trip_number, 'metadata' => ['trip_id' => $trip->id, 'stop_number' => $index + 1],
                ]);
            }
        });
        return back()->with('success', 'تم إنشاء الرحلة وربط الطلبات بنفس المندوب دون دمج حساباتها.');
    }

    public function start(int $trip): RedirectResponse
    {
        $this->authorizeManagement();
        $restaurant = app(TenantContext::class)->restaurant();
        DB::transaction(function () use ($trip, $restaurant) {
            $record = DeliveryTrip::query()->where('restaurant_id', $restaurant->id)->lockForUpdate()->findOrFail($trip);
            abort_unless($record->status === 'planned', 422, 'لا يمكن بدء الرحلة إلا إذا كانت مخططة.');
            $orders = $record->orders()->lockForUpdate()->get();
            abort_if($orders->count() < 2 || $orders->contains(fn ($order) => $order->delivery_status !== 'assigned'), 422, 'تأكد أن كل الطلبات ما زالت مسندة ولم يبدأ توصيلها منفردًا.');
            $record->update(['status' => 'in_progress', 'started_at' => now()]);
            foreach ($orders as $order) $order->deliveryEvents()->create(['restaurant_id' => $restaurant->id, 'delivery_rider_id' => $record->delivery_rider_id,
                'actor_id' => request()->user()->id, 'event_type' => 'trip_started', 'from_status' => 'assigned', 'to_status' => 'assigned',
                'notes' => 'بدأت رحلة ' . $record->trip_number, 'metadata' => ['trip_id' => $record->id]]);
        });
        return back()->with('success', 'بدأت الرحلة. يحدّث المندوب حالة كل طلب عند التسليم كالمعتاد.');
    }

    public function riderStart(Request $request, int $trip): RedirectResponse
    {
        $rider = $request->user()->deliveryRider()->where('status', 'active')->firstOrFail();
        DB::transaction(function () use ($trip, $rider, $request) {
            $record = DeliveryTrip::query()->where('delivery_rider_id', $rider->id)->lockForUpdate()->findOrFail($trip);
            abort_unless($record->status === 'planned', 422, 'الرحلة ليست في حالة انتظار الانطلاق.');
            $orders = $record->orders()->lockForUpdate()->get();
            abort_if($orders->count() < 2 || $orders->contains(fn ($order) => $order->delivery_status !== 'assigned'), 422, 'تأكد أن كل الطلبات ما زالت جاهزة للاستلام.');
            $record->update(['status' => 'in_progress', 'started_at' => now()]);
            foreach ($orders as $order) $order->deliveryEvents()->create([
                'restaurant_id' => $record->restaurant_id, 'delivery_rider_id' => $rider->id, 'actor_id' => $request->user()->id,
                'event_type' => 'trip_started', 'from_status' => 'assigned', 'to_status' => 'assigned',
                'notes' => 'بدأ المندوب الرحلة ' . $record->trip_number, 'metadata' => ['trip_id' => $record->id],
            ]);
        });
        return back()->with('success', 'انطلقت الرحلة. اتبع ترتيب المحطات وحدّث حالة كل طلب.');
    }

    private function authorizeManagement(): void
    {
        $user = request()->user(); $restaurant = app(TenantContext::class)->restaurant();
        abort_unless($user?->isOwnerOf($restaurant) || $user?->roleIn($restaurant) === 'manager', 403);
    }
}
