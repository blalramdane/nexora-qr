<?php

namespace App\Http\Controllers;

use App\Models\DeliveryRider;
use App\Models\DeliveryTrip;
use App\Models\Order;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryRiderController extends Controller
{
    public function showRegister(): Response
    {
        return Inertia::render('Delivery/Register');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'vehicle_type' => ['required', 'in:walking,bicycle,motorcycle,car'],
            'coverage_areas' => ['required', 'string', 'max:500'],
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
            $areas = collect(preg_split('/[,،\n]+/u', $data['coverage_areas']))
                ->map(fn ($area) => trim($area))->filter()->unique()->take(20)->values()->all();
            abort_if(count($areas) === 0, 422, 'اكتب منطقة تغطية واحدة على الأقل.');
            $user->deliveryRider()->create([
                'phone' => $data['phone'], 'vehicle_type' => $data['vehicle_type'],
                'coverage_areas' => $areas, 'status' => 'active',
            ]);
            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();
        return redirect()->route('delivery.dashboard')->with('success', 'تم إنشاء حسابك. تقدر تستقبل دعوات المطاعم وتختار التعاون المناسب لك.');
    }

    public function dashboard(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $rider = $user->deliveryRider()->with(['restaurants' => fn ($query) => $query->wherePivotIn('status', ['pending', 'active'])])->first();

        if (!$rider) {
            if ($user->restaurants()->exists()) {
                return redirect()->route('dashboard');
            }

            abort(403, 'الحساب الحالي غير مسجل كمندوب توصيل.');
        }
        $orders = Order::query()->where('delivery_rider_id', $rider->id)
            ->whereHas('restaurant', fn ($query) => $query->where('is_active', true))
            ->with(['restaurant:id,name,phone,whatsapp_phone', 'items:id,order_id,product_name,quantity,variant_name'])
            ->latest()->limit(100)->get();

        $trips = DeliveryTrip::query()->where('delivery_rider_id', $rider->id)
            ->whereIn('status', ['planned', 'in_progress'])
            ->whereHas('restaurant', fn ($query) => $query->where('is_active', true))
            ->with(['restaurant:id,name', 'orders' => fn ($query) => $query->withPivot('stop_number')])
            ->latest()->limit(30)->get()->map(fn ($trip) => [
                'id' => $trip->id, 'trip_number' => $trip->trip_number, 'status' => $trip->status,
                'notes' => $trip->notes, 'started_at' => $trip->started_at?->toIso8601String(),
                'restaurant_name' => $trip->restaurant->name,
                'orders' => $trip->orders->map(fn ($order) => [
                    'id' => $order->id, 'order_number' => $order->order_number, 'customer_name' => $order->customer_name,
                    'customer_phone' => $order->customer_phone, 'delivery_address' => $order->delivery_address,
                    'delivery_area' => $order->delivery_area, 'delivery_status' => $order->delivery_status,
                    'stop_number' => $order->pivot->stop_number,
                ])->values(),
            ])->values();

        $coverageAreas = collect($rider->coverage_areas ?? [])->map(fn ($area) => mb_strtolower(trim((string) $area)))->filter()->unique()->values();
        $availableOrders = collect();
        if ($rider->status === 'active' && $rider->open_to_area_orders && $coverageAreas->isNotEmpty()) {
            $availableOrders = Order::query()
                ->whereNull('delivery_rider_id')->where('fulfillment_type', 'delivery')
                ->whereIn('delivery_status', ['unassigned', 'failed'])
                ->whereIn('status', ['ready'])
                ->whereHas('restaurant', fn ($query) => $query->where('is_active', true)->where('delivery_enabled', true))
                ->with('restaurant:id,name')
                ->latest()->limit(200)->get()
                ->filter(function ($order) use ($coverageAreas) {
                    $area = mb_strtolower(trim((string) $order->delivery_area));
                    return $area !== '' && $coverageAreas->contains($area);
                })
                ->map(fn ($order) => [
                    'id' => $order->id, 'order_number' => $order->order_number,
                    'restaurant_name' => $order->restaurant->name, 'delivery_area' => $order->delivery_area,
                    'items_count' => $order->items_count, 'delivery_fee' => $order->delivery_fee,
                    'created_at' => $order->created_at?->toIso8601String(),
                ])->values();
        }

        return Inertia::render('Delivery/Dashboard', [
            'rider' => $rider,
            'riderName' => $request->user()->name,
            'invites' => $rider->restaurants->map(fn ($restaurant) => [
                'id' => $restaurant->id, 'name' => $restaurant->name,
                'status' => $restaurant->pivot->status,
            ])->values(),
            'orders' => $orders,
            'trips' => $trips,
            'availableOrders' => $availableOrders,
        ]);
    }

    public function claimAvailableOrder(Request $request, int $order): RedirectResponse
    {
        $rider = $request->user()->deliveryRider()->where('status', 'active')->firstOrFail();
        abort_unless($rider->open_to_area_orders, 403, 'فعّل إتاحة ملفك أولًا لاستلام الطلبات المتاحة.');

        try {
            DB::transaction(function () use ($rider, $order, $request) {
            $record = Order::query()->lockForUpdate()->findOrFail($order);
            $coverageAreas = collect($rider->coverage_areas ?? [])->map(fn ($area) => mb_strtolower(trim((string) $area)));
            $orderArea = mb_strtolower(trim((string) $record->delivery_area));

            abort_unless($record->fulfillment_type === 'delivery' && in_array($record->status, ['ready'], true), 422, 'الطلب غير متاح للاستلام حاليًا.');
            abort_unless($record->delivery_rider_id === null && in_array($record->delivery_status, ['unassigned', 'failed'], true), 409, 'مندوب آخر استلم الطلب بالفعل.');
            abort_unless($orderArea !== '' && $coverageAreas->contains($orderArea), 403, 'الطلب خارج مناطق التغطية المسجلة في حسابك.');
            abort_unless($record->restaurant()->where('is_active', true)->where('delivery_enabled', true)->exists(), 422, 'المطعم لا يستقبل طلبات توصيل حاليًا.');

            $previousStatus = $record->delivery_status;
            $record->update([
                'delivery_rider_id' => $rider->id,
                'delivery_status' => 'assigned',
                'delivery_assigned_at' => now(),
            ]);
            $record->deliveryEvents()->create([
                'restaurant_id' => $record->restaurant_id,
                'delivery_rider_id' => $rider->id,
                'actor_id' => $request->user()->id,
                'event_type' => 'claimed_by_rider',
                'from_status' => $previousStatus,
                'to_status' => 'assigned',
                'notes' => 'استلم المندوب الطلب من سوق الطلبات المتاحة.',
                'metadata' => ['claim_source' => 'rider_marketplace'],
            ]);
            });
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception) {
            if ($exception->getStatusCode() === 409) {
                return back()->with('error', 'الطلب ده مندوب تاني استلمه بالفعل. حدّث القائمة واختار طلبًا متاحًا آخر.');
            }

            throw $exception;
        }

        return redirect()->route('delivery.dashboard')->with('success', 'تم استلام الطلب بنجاح. ظهرت لك بيانات العميل في قائمة طلباتك.');
    }

    public function respondToInvite(Request $request, int $restaurant): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', 'in:accept,decline']]);
        $rider = $request->user()->deliveryRider()->firstOrFail();
        abort_unless($rider->status === 'active', 403, 'حساب المندوب لم يتم تفعيله بعد.');
        $membership = DB::table('restaurant_delivery_riders')->where('restaurant_id', $restaurant)->where('delivery_rider_id', $rider->id)->first();
        abort_unless($membership && $membership->status === 'pending', 404);
        DB::table('restaurant_delivery_riders')->where('id', $membership->id)->update([
            'status' => $data['decision'] === 'accept' ? 'active' : 'declined', 'updated_at' => now(),
        ]);
        return back()->with('success', $data['decision'] === 'accept' ? 'تم قبول التعاون مع المطعم.' : 'تم رفض الدعوة.');
    }

    public function updateMarketplaceAvailability(Request $request): RedirectResponse
    {
        $data = $request->validate(['open_to_area_orders' => ['required', 'boolean']]);
        $rider = $request->user()->deliveryRider()->where('status', 'active')->firstOrFail();
        $rider->update(['open_to_area_orders' => (bool) $data['open_to_area_orders']]);

        return back()->with('success', $rider->open_to_area_orders
            ? 'تم تفعيل ظهورك للمطاعم في مناطق تغطيتك. استلام الطلبات يظل بعد موافقة المطعم وإسناد الطلب لك.'
            : 'تم إيقاف ظهورك في قائمة المندوبين المتاحين حسب المنطقة.');
    }

    public function updateDeliveryStatus(Request $request, int $order): RedirectResponse
    {
        $data = $request->validate(['delivery_status' => ['required', 'in:picked_up,delivered,failed'], 'delivery_notes' => ['nullable', 'string', 'max:500']]);
        $rider = $request->user()->deliveryRider()->where('status', 'active')->firstOrFail();
        DB::transaction(function () use ($data, $order, $rider, $request) {
            $record = Order::query()->where('delivery_rider_id', $rider->id)->lockForUpdate()->findOrFail($order);
            $trip = $record->deliveryTrips()->first();
            abort_if($trip && $trip->status === 'planned', 422, 'لا يمكن تحديث الطلب قبل انطلاق الرحلة من حساب المندوب.');
            abort_if($trip && $trip->status !== 'in_progress', 422, 'الرحلة غير نشطة؛ راجع المطعم قبل تحديث الطلب.');
            $allowed = ['assigned' => ['picked_up', 'failed'], 'picked_up' => ['delivered', 'failed']];
            abort_unless(in_array($data['delivery_status'], $allowed[$record->delivery_status] ?? [], true), 422, 'تحديث حالة التوصيل غير مسموح.');
            $previousStatus = $record->delivery_status;
            $updates = ['delivery_status' => $data['delivery_status'], 'delivery_notes' => $data['delivery_notes'] ?? $record->delivery_notes];
            if ($data['delivery_status'] === 'picked_up') $updates['delivery_picked_up_at'] = now();
            if ($data['delivery_status'] === 'delivered') $updates['delivered_at'] = now();
            $record->update($updates);
            $record->deliveryEvents()->create([
                'restaurant_id' => $record->restaurant_id, 'delivery_rider_id' => $rider->id, 'actor_id' => $request->user()->id,
                'event_type' => $data['delivery_status'], 'from_status' => $previousStatus, 'to_status' => $data['delivery_status'],
                'notes' => $data['delivery_notes'] ?? null,
            ]);
            if (in_array($data['delivery_status'], ['delivered', 'failed'], true)) {
                foreach ($record->deliveryTrips()->whereIn('delivery_trips.status', ['planned', 'in_progress'])->get() as $trip) {
                    $allStopsResolved = $trip->orders()->whereNotIn('delivery_status', ['delivered', 'failed'])->doesntExist();
                    if ($allStopsResolved) $trip->update(['status' => 'completed', 'completed_at' => now()]);
                }
            }
        });
        return back()->with('success', 'تم تحديث حالة التوصيل.');
    }
}
