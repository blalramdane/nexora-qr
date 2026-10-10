<?php

namespace App\Http\Controllers;

use App\Models\DeliveryRider;
use App\Models\DeliveryRiderLedgerEntry;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryRiderLedgerController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeManagement();
        $restaurantId = app(TenantContext::class)->id();
        $riders = $this->assignedRiders($restaurantId);
        $selectedId = $request->integer('rider_id') ?: ($riders->first()['id'] ?? null);
        $selected = $riders->firstWhere('id', $selectedId);
        $entries = collect();
        $summary = ['opening_balance' => 0, 'advances' => 0, 'expenses' => 0, 'repayments' => 0, 'collections' => 0, 'handovers' => 0, 'balance_due' => 0];

        if ($selected) {
            $query = DeliveryRiderLedgerEntry::query()->where('restaurant_id', $restaurantId)->where('delivery_rider_id', $selectedId);
            $all = (clone $query)->orderBy('occurred_at')->orderBy('id')->get();
            foreach ($all as $entry) {
                $amount = (float) $entry->amount;
                $bucket = ['advance' => 'advances', 'expense' => 'expenses', 'repayment' => 'repayments', 'collection' => 'collections', 'handover' => 'handovers'][$entry->type] ?? $entry->type;
                if (array_key_exists($bucket, $summary)) $summary[$bucket] += $amount;
                $summary['balance_due'] += in_array($entry->type, ['opening_balance', 'collection', 'repayment', 'adjustment_debit'], true) ? $amount : (in_array($entry->type, ['advance', 'expense', 'handover', 'adjustment_credit'], true) ? -$amount : 0);
            }
            $entries = (clone $query)->with('createdBy:id,name')->latest('occurred_at')->latest('id')->limit(150)->get()->map(fn ($entry) => [
                'id' => $entry->id, 'type' => $entry->type, 'amount' => (float) $entry->amount, 'reference' => $entry->reference,
                'description' => $entry->description, 'occurred_at' => $entry->occurred_at?->toISOString(), 'created_by' => $entry->createdBy?->name,
            ])->values();
            foreach (['opening_balance','advances','expenses','repayments','collections','handovers','balance_due'] as $key) $summary[$key] = round($summary[$key], 2);
        }

        return Inertia::render('Delivery/Ledger', ['riders' => $riders, 'selectedRiderId' => $selectedId, 'entries' => $entries, 'summary' => $summary]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManagement();
        $restaurantId = app(TenantContext::class)->id();
        $data = $request->validate([
            'delivery_rider_id' => ['required', 'integer'],
            'type' => ['required', 'in:opening_balance,advance,expense,repayment,adjustment_debit,adjustment_credit'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'description' => ['required', 'string', 'min:3', 'max:2000'],
            'reference' => ['nullable', 'string', 'max:100'], 'occurred_at' => ['nullable', 'date'],
        ]);

        $rider = DeliveryRider::query()->where('status', 'active')->findOrFail($data['delivery_rider_id']);
        abort_unless(DB::table('restaurant_delivery_riders')->where('restaurant_id', $restaurantId)->where('delivery_rider_id', $rider->id)->where('status', 'active')->exists(), 404);

        DB::transaction(function () use ($data, $restaurantId, $request, $rider) {
            if ($data['type'] === 'opening_balance') {
                abort_if(DeliveryRiderLedgerEntry::query()->where('restaurant_id', $restaurantId)->where('delivery_rider_id', $rider->id)->where('type', 'opening_balance')->exists(), 422, 'تم تسجيل رصيد افتتاحي لهذا المندوب بالفعل. استخدم قيد تسوية بدلًا منه.');
            }
            DeliveryRiderLedgerEntry::create([
                'restaurant_id' => $restaurantId, 'delivery_rider_id' => $rider->id, 'created_by' => $request->user()->id,
                'type' => $data['type'], 'amount' => $data['amount'], 'reference' => $data['reference'] ?? null,
                'description' => $data['description'], 'occurred_at' => $data['occurred_at'] ?? now(),
            ]);
        });

        return to_route('delivery.ledger.index', ['rider_id' => $rider->id])->with('success', 'تم تسجيل الحركة في كشف حساب المندوب.');
    }

    private function assignedRiders(int $restaurantId)
    {
        return DeliveryRider::query()->where('status', 'active')->whereIn('id', DB::table('restaurant_delivery_riders')->select('delivery_rider_id')->where('restaurant_id', $restaurantId)->where('status', 'active'))
            ->with('user:id,name')->get()->map(fn ($r) => ['id' => $r->id, 'name' => $r->user?->name ?? 'مندوب']);
    }

    private function authorizeManagement(): void
    {
        $user = request()->user(); $restaurant = app(TenantContext::class)->restaurant();
        abort_unless($user?->isOwnerOf($restaurant) || $user?->roleIn($restaurant) === 'manager', 403);
    }
}
