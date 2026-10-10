<?php

namespace App\Http\Controllers;

use App\Models\DeliveryRider;
use App\Models\DeliveryShiftClosing;
use App\Models\Order;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryShiftClosingController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeManagement();
        $restaurantId = app(TenantContext::class)->id();
        $riders = $this->assignedRiders($restaurantId);
        $selectedId = $request->integer('rider_id') ?: ($riders->first()['id'] ?? null);
        $selectedRider = $riders->firstWhere('id', $selectedId);
        $date = $request->query('date', now()->toDateString());
        if (! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || ! \Illuminate\Support\Carbon::canBeCreatedFromFormat($date, 'Y-m-d')) {
            $date = now()->toDateString();
        }

        $expectedOrders = collect();
        if ($selectedRider) {
            $expectedOrders = Order::query()->where('restaurant_id', $restaurantId)->where('delivery_rider_id', $selectedId)
                ->where('fulfillment_type', 'delivery')->where('delivery_status', 'delivered')
                ->whereIn('payment_method', ['cash_on_delivery', 'cash'])->whereDate('delivered_at', $date)
                ->with('cashSettlements:id')->orderBy('delivered_at')->get();
        }

        $closings = DeliveryShiftClosing::query()->where('restaurant_id', $restaurantId)->whereDate('shift_date', $date)
            ->with('rider.user:id,name')->latest('closed_at')->limit(100)->get()->map(fn ($closing) => [
                'id' => $closing->id, 'rider_name' => $closing->rider?->user?->name ?? 'مندوب غير متاح',
                'rider_id' => $closing->delivery_rider_id, 'shift_date' => $closing->shift_date->toDateString(), 'shift_name' => $closing->shift_name,
                'expected_amount' => (float) $closing->expected_amount, 'collected_amount' => (float) $closing->collected_amount,
                'handed_over_amount' => (float) $closing->handed_over_amount, 'collection_difference' => (float) $closing->collection_difference,
                'handover_difference' => (float) $closing->handover_difference, 'notes' => $closing->notes, 'closed_at' => $closing->closed_at?->toISOString(),
            ])->values();

        return Inertia::render('Delivery/ShiftClosings', [
            'riders' => $riders, 'selectedRiderId' => $selectedId, 'date' => $date,
            'orders' => $expectedOrders->map(fn ($order) => [
                'id' => $order->id, 'order_number' => $order->order_number, 'total' => (float) $order->total,
                'delivered_at' => $order->delivered_at?->toISOString(), 'settled' => $order->cashSettlements->isNotEmpty(),
                'payment_status' => $order->payment_status,
            ])->values(),
            'summary' => [
                'orders_count' => $expectedOrders->count(),
                'expected_amount' => round((float) $expectedOrders->sum(fn ($order) => (float) $order->total), 2),
                'settled_orders' => $expectedOrders->filter(fn ($order) => $order->cashSettlements->isNotEmpty())->count(),
                'unsettled_orders' => $expectedOrders->filter(fn ($order) => $order->cashSettlements->isEmpty())->count(),
            ],
            'closings' => $closings,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManagement();
        $restaurantId = app(TenantContext::class)->id();
        $data = $request->validate([
            'delivery_rider_id' => ['required', 'integer'], 'shift_date' => ['required', 'date_format:Y-m-d'],
            'shift_name' => ['required', 'string', 'min:2', 'max:40'],
            'collected_amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'handed_over_amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        abort_unless((float) $data['handed_over_amount'] <= (float) $data['collected_amount'], 422, 'المبلغ المسلّم للمطعم لا يمكن أن يتجاوز المبلغ المحصّل.');

        $rider = DeliveryRider::query()->where('status', 'active')->findOrFail($data['delivery_rider_id']);
        abort_unless(DB::table('restaurant_delivery_riders')->where('restaurant_id', $restaurantId)->where('delivery_rider_id', $rider->id)->where('status', 'active')->exists(), 404);
        $expected = (float) Order::query()->where('restaurant_id', $restaurantId)->where('delivery_rider_id', $rider->id)
            ->where('fulfillment_type', 'delivery')->where('delivery_status', 'delivered')->whereIn('payment_method', ['cash_on_delivery', 'cash'])
            ->whereDate('delivered_at', $data['shift_date'])->sum('total');
        $collectionDifference = round($expected - (float) $data['collected_amount'], 2);
        $handoverDifference = round((float) $data['collected_amount'] - (float) $data['handed_over_amount'], 2);
        abort_if(($collectionDifference != 0 || $handoverDifference != 0) && trim($data['notes'] ?? '') === '', 422, 'اكتب سبب فرق التحصيل أو التسليم قبل إقفال الوردية.');

        try {
            DeliveryShiftClosing::create([
                'restaurant_id' => $restaurantId, 'delivery_rider_id' => $rider->id, 'closed_by' => $request->user()->id,
                'shift_date' => $data['shift_date'], 'shift_name' => trim($data['shift_name']), 'expected_amount' => $expected,
                'collected_amount' => $data['collected_amount'], 'handed_over_amount' => $data['handed_over_amount'],
                'collection_difference' => $collectionDifference, 'handover_difference' => $handoverDifference,
                'notes' => $data['notes'] ?? null, 'closed_at' => now(),
            ]);
        } catch (QueryException $exception) {
            if (str_contains(strtolower($exception->getMessage()), 'unique') || str_contains(strtolower($exception->getMessage()), 'duplicate')) {
                return to_route('delivery.shift-closings.index', ['rider_id' => $rider->id, 'date' => $data['shift_date']])
                    ->withErrors(['shift_name' => 'الوردية دي اتقفلت قبل كده لنفس المندوب والتاريخ.']);
            }
            throw $exception;
        }

        return to_route('delivery.shift-closings.index', ['rider_id' => $rider->id, 'date' => $data['shift_date']])
            ->with('success', 'تم إقفال الوردية وحفظ المطابقة والفروق.');
    }

    private function assignedRiders(int $restaurantId)
    {
        return DeliveryRider::query()->where('status', 'active')->whereIn('id', DB::table('restaurant_delivery_riders')->select('delivery_rider_id')->where('restaurant_id', $restaurantId)->where('status', 'active'))
            ->with('user:id,name')->get()->map(fn ($rider) => ['id' => $rider->id, 'name' => $rider->user?->name ?? 'مندوب']);
    }

    private function authorizeManagement(): void
    {
        $user = request()->user();
        $restaurant = app(TenantContext::class)->restaurant();
        abort_unless($user?->isOwnerOf($restaurant) || $user?->roleIn($restaurant) === 'manager', 403);
    }
}
