<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QrBranch;
use App\Models\QrOrder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QrMenuController extends Controller
{
    public function show(string $slug): JsonResponse
    {
        $branch = QrBranch::query()->where('slug', $slug)->where('is_active', true)->firstOrFail();

        return response()->json([
            'branch' => ['name' => $branch->name, 'slug' => $branch->slug, 'currency' => $branch->currency],
            'menu_version' => $branch->menu_version,
            'items' => $branch->menuItems()->where('is_available', true)->orderBy('category_name')->orderBy('name')
                ->get(['source_product_id', 'name', 'category_name', 'description', 'price_minor', 'currency', 'options']),
        ])->header('Cache-Control', 'public, max-age=30');
    }

    public function store(Request $request, string $slug): JsonResponse
    {
        $branch = QrBranch::query()->where('slug', $slug)->where('is_active', true)->firstOrFail();
        $data = $request->validate([
            'source_order_uuid' => ['required', 'uuid'],
            'menu_version' => ['required', 'integer', 'min:1'],
            'fulfillment_type' => ['required', 'in:takeaway,dine_in,delivery'],
            'customer_name' => ['nullable', 'string', 'max:120'],
            'customer_phone' => ['required_if:fulfillment_type,delivery', 'nullable', 'string', 'max:40'],
            'customer_address' => ['required_if:fulfillment_type,delivery', 'nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:80'],
            'items.*.source_product_id' => ['required', 'string', 'max:100', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.options' => ['sometimes', 'array', 'size:0'],
        ]);

        // Hash the validated customer intent, not current product prices. A retry must
        // receive the original result even if the menu changed after the first submit.
        $intentItems = array_map(fn (array $item) => [
            'source_product_id' => $item['source_product_id'],
            'quantity' => (int) $item['quantity'],
            'options' => [],
        ], $data['items']);
        $normalized = [
            'source_order_uuid' => $data['source_order_uuid'],
            'menu_version' => (int) $data['menu_version'],
            'fulfillment_type' => $data['fulfillment_type'],
            'customer_name' => $data['customer_name'] ?? null,
            'customer_phone' => $data['customer_phone'] ?? null,
            'customer_address' => $data['customer_address'] ?? null,
            'notes' => $data['notes'] ?? null,
            'items' => $intentItems,
        ];
        $hash = hash('sha256', json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        $existing = $branch->orders()->where('source_order_uuid', $data['source_order_uuid'])->first();
        if ($existing) {
            return $this->idempotentResponse($existing, $hash);
        }

        if ((int) $data['menu_version'] !== (int) $branch->menu_version) {
            return response()->json([
                'message' => 'Refresh the menu before submitting.',
                'code' => 'MENU_VERSION_STALE',
                'menu_version' => $branch->menu_version,
            ], 409);
        }

        $ids = collect($data['items'])->pluck('source_product_id')->unique()->values();
        $catalog = $branch->menuItems()->whereIn('source_product_id', $ids)->where('is_available', true)->get()->keyBy('source_product_id');
        if ($catalog->count() !== $ids->count()) {
            throw ValidationException::withMessages(['items' => 'One or more products are unavailable.']);
        }

        $snapshot = [];
        $subtotal = 0;
        foreach ($data['items'] as $item) {
            $product = $catalog->get($item['source_product_id']);
            $quantity = (int) $item['quantity'];
            $unit = (int) $product->price_minor;
            if ($unit > intdiv(PHP_INT_MAX, $quantity) || $subtotal > PHP_INT_MAX - ($unit * $quantity)) {
                throw ValidationException::withMessages(['items' => 'Order total is outside the supported range.']);
            }
            $line = $unit * $quantity;
            $subtotal += $line;
            $snapshot[] = [
                'source_product_id' => $product->source_product_id,
                'name' => $product->name,
                'quantity' => $quantity,
                'unit_price_minor' => $unit,
                'line_total_minor' => $line,
                'options' => [],
            ];
        }

        try {
            $order = DB::transaction(fn () => $branch->orders()->create([
                'source_order_uuid' => $data['source_order_uuid'],
                'status' => 'pending_delivery',
                'customer_name' => $data['customer_name'] ?? null,
                'customer_phone' => $data['customer_phone'] ?? null,
                'customer_address' => $data['customer_address'] ?? null,
                'notes' => $data['notes'] ?? null,
                'fulfillment_type' => $data['fulfillment_type'],
                'subtotal_minor' => $subtotal,
                'currency' => $branch->currency,
                'menu_version' => $branch->menu_version,
                'items_snapshot' => $snapshot,
                'payload_hash' => $hash,
                'submitted_at' => now(),
            ]));
        } catch (QueryException $exception) {
            $existing = $branch->orders()->where('source_order_uuid', $data['source_order_uuid'])->first();
            if (! $existing) {
                throw $exception;
            }
            return $this->idempotentResponse($existing, $hash);
        }

        return response()->json($this->publicOrder($order), 202);
    }

    private function idempotentResponse(QrOrder $order, string $hash): JsonResponse
    {
        if (! hash_equals($order->payload_hash, $hash)) {
            return response()->json(['message' => 'Request ID reused with a different payload.', 'code' => 'IDEMPOTENCY_CONFLICT'], 409);
        }
        return response()->json($this->publicOrder($order), 200);
    }

    private function publicOrder(QrOrder $order): array
    {
        return [
            'order_id' => $order->id,
            'source_order_uuid' => $order->source_order_uuid,
            'status' => $order->status,
            'subtotal_minor' => $order->subtotal_minor,
            'currency' => $order->currency,
            'submitted_at' => $order->submitted_at?->toIso8601String(),
        ];
    }
}
