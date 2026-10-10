<?php

namespace App\Http\Controllers;

use App\Models\DeliveryRider;
use App\Models\Order;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RestaurantDeliveryController extends Controller
{
    public function index(): Response
    {
        $restaurant = app(TenantContext::class)->restaurant();
        $this->authorizeManagement();
        $restaurantAreas = collect($restaurant->delivery_areas ?? []);
        $riders = $restaurant->deliveryRiders()->with('user:id,name,email')->orderByPivot('created_at', 'desc')->get();
        $available = DeliveryRider::query()->where('status', 'active')->where('open_to_area_orders', true)
            ->whereDoesntHave('restaurants', fn ($query) => $query->where('restaurants.id', $restaurant->id))
            ->with('user:id,name,email')->latest()->limit(100)->get()
            ->filter(fn ($rider) => $restaurantAreas->isEmpty() || count(array_intersect($restaurantAreas->all(), $rider->coverage_areas ?? [])) > 0)->values();
        $orders = Order::query()->where('restaurant_id', $restaurant->id)->where('fulfillment_type', 'delivery')
            ->with(['deliveryRider.user:id,name', 'items:id,order_id,product_name,quantity', 'deliveryEvents.actor:id,name', 'deliveryEvents.rider.user:id,name'])
            ->latest()->limit(100)->get();
        $now = now();
        $delayedOrders = $orders->filter(function ($order) use ($now) {
            if (in_array($order->delivery_status, ['delivered', 'failed', 'unassigned'], true) || in_array($order->status, ['cancelled', 'completed'], true)) return false;
            $reference = $order->delivery_status === 'picked_up' ? $order->delivery_picked_up_at : $order->delivery_assigned_at;
            $thresholdMinutes = $order->delivery_status === 'picked_up' ? 45 : 15;
            return $reference && $reference->lte($now->copy()->subMinutes($thresholdMinutes));
        })->map(fn ($order) => [
            'id' => $order->id, 'order_number' => $order->order_number, 'customer_name' => $order->customer_name,
            'delivery_status' => $order->delivery_status, 'delivery_area' => $order->delivery_area,
            'delivery_address' => $order->delivery_address, 'rider_name' => $order->deliveryRider?->user?->name,
            'elapsed_minutes' => (int) ($order->delivery_status === 'picked_up' ? $order->delivery_picked_up_at : $order->delivery_assigned_at)->diffInMinutes($now),
            'threshold_minutes' => $order->delivery_status === 'picked_up' ? 45 : 15,
        ])->values();

        return Inertia::render('Delivery/Manage', [
            'restaurant' => ['id' => $restaurant->id, 'name' => $restaurant->name],
            'deliveryAreas' => $restaurantAreas->values(),
            'riders' => $riders->map(fn ($rider) => [
                'id' => $rider->id, 'name' => $rider->user->name, 'email' => $rider->user->email,
                'phone' => $rider->phone, 'vehicle_type' => $rider->vehicle_type,
                'coverage_areas' => $rider->coverage_areas, 'rider_status' => $rider->status,
                'link_status' => $rider->pivot->status,
            ])->values(),
            'availableRiders' => $available->map(fn ($rider) => [
                'id' => $rider->id, 'name' => $rider->user->name, 'email' => $rider->user->email,
                'phone' => $rider->phone, 'vehicle_type' => $rider->vehicle_type, 'coverage_areas' => $rider->coverage_areas,
            ])->values(),
            'orders' => $orders,
            'delayedOrders' => $delayedOrders,
        ]);
    }

    public function invite(Request $request): RedirectResponse
    {
        $this->authorizeManagement();
        $data = $request->validate(['delivery_rider_id' => ['required', 'integer', Rule::exists('delivery_riders', 'id')->where('status', 'active')]]);
        $restaurant = app(TenantContext::class)->restaurant();
        $rider = DeliveryRider::query()->where('status', 'active')->findOrFail($data['delivery_rider_id']);
        abort_if($restaurant->deliveryRiders()->whereKey($rider->id)->exists(), 422, 'المندوب مرتبط بالمطعم بالفعل.');
        $restaurant->deliveryRiders()->attach($rider->id, ['status' => 'pending', 'invited_by' => request()->user()->id]);
        return back()->with('success', 'تم إرسال دعوة للمندوب، ولن يستلم طلبات قبل قبوله.');
    }

    public function updateRider(Request $request, int $rider): RedirectResponse
    {
        $this->authorizeManagement();
        $data = $request->validate(['status' => ['required', 'in:active,suspended']]);
        $restaurant = app(TenantContext::class)->restaurant();
        $updated = $restaurant->deliveryRiders()->updateExistingPivot($rider, ['status' => $data['status']]);
        abort_unless($updated, 404);
        return back()->with('success', $data['status'] === 'active' ? 'تم تفعيل المندوب داخل المطعم.' : 'تم إيقاف المندوب عن استقبال طلبات المطعم.');
    }

    public function assign(Request $request, int $order): RedirectResponse
    {
        $this->authorizeManagement();
        $data = $request->validate(['delivery_rider_id' => ['required', 'integer']]);
        $restaurant = app(TenantContext::class)->restaurant();
        $rider = DeliveryRider::query()->where('status', 'active')->findOrFail($data['delivery_rider_id']);
        abort_unless($restaurant->deliveryRiders()->whereKey($rider->id)->wherePivot('status', 'active')->exists(), 422, 'المندوب غير معتمد لهذا المطعم.');

        DB::transaction(function () use ($restaurant, $order, $rider, $request) {
            $record = Order::query()->where('restaurant_id', $restaurant->id)->where('fulfillment_type', 'delivery')->lockForUpdate()->findOrFail($order);
            abort_unless(!in_array($record->status, ['cancelled', 'completed'], true), 422, 'لا يمكن تعيين طلب منتهي أو ملغي.');
            abort_unless(in_array($record->delivery_status, ['unassigned', 'assigned', 'failed'], true), 422, 'الطلب بدأ التوصيل بالفعل ولا يمكن إعادة تعيينه.');
            $previousRiderId = $record->delivery_rider_id;
            $previousStatus = $record->delivery_status;
            $record->update(['delivery_rider_id' => $rider->id, 'delivery_status' => 'assigned', 'delivery_assigned_at' => now()]);
            $record->deliveryEvents()->create([
                'restaurant_id' => $restaurant->id, 'delivery_rider_id' => $rider->id, 'actor_id' => $request->user()->id,
                'event_type' => $previousRiderId && $previousRiderId !== $rider->id ? 'reassigned' : 'assigned',
                'from_status' => $previousStatus, 'to_status' => 'assigned',
                'notes' => $previousRiderId && $previousRiderId !== $rider->id ? 'إعادة إسناد الطلب إلى مندوب آخر.' : 'إسناد الطلب للمندوب.',
                'metadata' => ['previous_rider_id' => $previousRiderId, 'new_rider_id' => $rider->id],
            ]);
        });
        return back()->with('success', 'تم إسناد الطلب للمندوب.');
    }

    private function authorizeManagement(): void
    {
        $user = request()->user();
        $restaurant = app(TenantContext::class)->restaurant();
        abort_unless($user?->isOwnerOf($restaurant) || $user?->roleIn($restaurant) === 'manager', 403);
    }
}
