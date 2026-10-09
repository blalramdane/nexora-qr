<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QrBranch;
use App\Models\QrMenuItem;
use App\Models\QrOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QrBranchAgentController extends Controller
{
    public function publishMenu(Request $request): JsonResponse
    {
        /** @var QrBranch $branch */
        $branch = $request->attributes->get('qr_branch');
        $data = $request->validate([
            'menu_version' => ['required', 'integer', 'min:1'],
            'items' => ['present', 'array', 'max:5000'],
            'items.*.source_product_id' => ['required', 'string', 'max:100'],
            'items.*.name' => ['required', 'string', 'max:180'],
            'items.*.category_name' => ['nullable', 'string', 'max:120'],
            'items.*.description' => ['nullable', 'string', 'max:2000'],
            'items.*.price_minor' => ['required', 'integer', 'min:0'],
            'items.*.currency' => ['nullable', 'string', 'max:8'],
            'items.*.is_available' => ['required', 'boolean'],
            'items.*.options' => ['nullable', 'array'],
        ]);

        if ((int) $data['menu_version'] <= (int) $branch->menu_version) {
            return response()->json([
                'message' => 'Menu version must be greater than the currently published version.',
                'current_menu_version' => $branch->menu_version,
            ], 409);
        }

        $seen = [];
        foreach ($data['items'] as $item) {
            if (isset($seen[$item['source_product_id']])) {
                return response()->json(['message' => 'Duplicate source_product_id in menu payload.'], 422);
            }
            $seen[$item['source_product_id']] = true;
        }

        DB::transaction(function () use ($branch, $data) {
            $incomingIds = collect($data['items'])->pluck('source_product_id')->all();
            foreach ($data['items'] as $item) {
                QrMenuItem::query()->updateOrCreate(
                    ['branch_id' => $branch->id, 'source_product_id' => $item['source_product_id']],
                    [
                        'name' => $item['name'],
                        'category_name' => $item['category_name'] ?? null,
                        'description' => $item['description'] ?? null,
                        'price_minor' => $item['price_minor'],
                        'currency' => $item['currency'] ?? $branch->currency,
                        'is_available' => $item['is_available'],
                        'options' => $item['options'] ?? null,
                    ],
                );
            }

            $branch->menuItems()->whereNotIn('source_product_id', $incomingIds)->update(['is_available' => false]);
            $branch->forceFill(['menu_version' => $data['menu_version']])->save();
        });

        return response()->json([
            'branch_slug' => $branch->slug,
            'menu_version' => (int) $data['menu_version'],
            'published_items' => count($data['items']),
        ]);
    }

    public function pendingOrders(Request $request): JsonResponse
    {
        /** @var QrBranch $branch */
        $branch = $request->attributes->get('qr_branch');
        $limit = min(max($request->integer('limit', 20), 1), 100);
        $leaseSeconds = min(max($request->integer('lease_seconds', 60), 15), 300);

        $orders = DB::transaction(function () use ($branch, $limit, $leaseSeconds) {
            $now = now();
            $pending = $branch->orders()
                ->where(function ($query) use ($now) {
                    $query->where('status', 'pending_delivery')
                        ->orWhere(function ($leased) use ($now) {
                            $leased->where('status', 'delivering')
                                ->where('delivery_lease_expires_at', '<=', $now);
                        });
                })
                ->orderBy('id')
                ->limit($limit)
                ->lockForUpdate()
                ->get();

            foreach ($pending as $order) {
                $order->forceFill([
                    'status' => 'delivering',
                    'delivery_lease_token' => (string) Str::uuid(),
                    'delivery_lease_expires_at' => $now->copy()->addSeconds($leaseSeconds),
                    'delivery_attempts' => $order->delivery_attempts + 1,
                ])->save();
            }

            return $pending;
        });

        return response()->json([
            'data' => $orders->map(fn (QrOrder $order) => [
                'id' => $order->id,
                'source_order_uuid' => $order->source_order_uuid,
                'delivery_lease_token' => $order->delivery_lease_token,
                'branch_slug' => $branch->slug,
                'status' => $order->status,
                'customer_name' => $order->customer_name,
                'customer_phone' => $order->customer_phone,
                'customer_address' => $order->customer_address,
                'notes' => $order->notes,
                'fulfillment_type' => $order->fulfillment_type,
                'subtotal_minor' => $order->subtotal_minor,
                'currency' => $order->currency,
                'menu_version' => $order->menu_version,
                'items' => $order->items_snapshot,
                'submitted_at' => $order->submitted_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    public function acknowledge(Request $request, int $orderId): JsonResponse
    {
        /** @var QrBranch $branch */
        $branch = $request->attributes->get('qr_branch');
        $data = $request->validate([
            'delivery_lease_token' => ['required', 'uuid'],
            'status' => ['required', 'in:imported,rejected'],
            'local_order_id' => ['nullable', 'string', 'max:100'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $order = $branch->orders()->whereKey($orderId)->firstOrFail();
        if ($order->status === $data['status']) {
            return response()->json(['data' => ['id' => $order->id, 'status' => $order->status]]);
        }

        if ($order->status !== 'delivering' || ! $order->delivery_lease_token || ! hash_equals($order->delivery_lease_token, $data['delivery_lease_token'])) {
            return response()->json(['message' => 'Delivery lease is stale or invalid.'], 409);
        }

        if ($data['status'] === 'imported' && empty($data['local_order_id'])) {
            return response()->json(['message' => 'local_order_id is required for imported orders.'], 422);
        }

        $order->forceFill([
            'status' => $data['status'],
            'local_order_id' => $data['local_order_id'] ?? null,
            'resolution_note' => $data['reason'] ?? null,
            'acknowledged_at' => now(),
            'delivery_lease_token' => null,
            'delivery_lease_expires_at' => null,
        ])->save();

        return response()->json(['data' => ['id' => $order->id, 'status' => $order->status]]);
    }
}
