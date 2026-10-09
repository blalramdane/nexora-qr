<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QrBranch;
use App\Models\QrOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QrOwnerDashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! in_array($user->qr_role, ['owner', 'branch_manager'], true)) {
            return response()->json(['message' => 'غير مصرح بعرض لوحة QR.'], 403);
        }
        if ($user->qr_role === 'branch_manager' && ! $user->qr_branch_id) {
            return response()->json(['message' => 'حساب مدير الفرع غير مربوط بفرع.'], 403);
        }

        $branchesQuery = QrBranch::query()
            ->when($user->qr_role === 'branch_manager', fn ($query) => $query->whereKey($user->qr_branch_id))
            ->withCount([
                'orders as pending_orders_count' => fn ($query) => $query->whereIn('status', ['pending_delivery', 'delivering']),
                'orders as received_orders_count' => fn ($query) => $query->where('status', 'received'),
                'orders as imported_today_count' => fn ($query) => $query->where('status', 'imported')->whereDate('acknowledged_at', today()),
                'orders as rejected_today_count' => fn ($query) => $query->where('status', 'rejected')->whereDate('acknowledged_at', today()),
            ])
            ->orderBy('name');

        $branches = $branchesQuery->get([
            'id', 'name', 'slug', 'code', 'currency', 'is_active', 'menu_version', 'last_seen_at',
        ])->map(fn (QrBranch $branch) => [
            'id' => $branch->id,
            'name' => $branch->name,
            'slug' => $branch->slug,
            'code' => $branch->code,
            'currency' => $branch->currency,
            'is_active' => $branch->is_active,
            'menu_version' => $branch->menu_version,
            'last_seen_at' => $branch->last_seen_at?->toIso8601String(),
            'connection_status' => $branch->last_seen_at && $branch->last_seen_at->greaterThan(now()->subMinutes(2))
                ? 'online'
                : 'offline_or_stale',
            'pending_orders_count' => (int) $branch->pending_orders_count,
            'received_orders_count' => (int) $branch->received_orders_count,
            'imported_today_count' => (int) $branch->imported_today_count,
            'rejected_today_count' => (int) $branch->rejected_today_count,
        ])->values();

        $branchIds = $branches->pluck('id')->all();
        $recentOrders = QrOrder::query()
            ->whereIn('branch_id', $branchIds)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get([
                'id', 'branch_id', 'source_order_uuid', 'status', 'customer_name',
                'fulfillment_type', 'subtotal_minor', 'currency', 'submitted_at',
                'received_at', 'acknowledged_at', 'resolution_note', 'local_order_id',
            ])
            ->map(fn (QrOrder $order) => [
                'id' => $order->id,
                'branch_id' => $order->branch_id,
                'source_order_uuid' => $order->source_order_uuid,
                'status' => $order->status,
                'customer_name' => $order->customer_name,
                'fulfillment_type' => $order->fulfillment_type,
                'subtotal_minor' => $order->subtotal_minor,
                'currency' => $order->currency,
                'submitted_at' => $order->submitted_at?->toIso8601String(),
                'received_at' => $order->received_at?->toIso8601String(),
                'acknowledged_at' => $order->acknowledged_at?->toIso8601String(),
                'resolution_note' => $order->resolution_note,
                'local_order_id' => $order->local_order_id,
            ])->values();

        return response()->json([
            'data_freshness' => 'cloud_qr_sync_state',
            'generated_at' => now()->toIso8601String(),
            'summary' => [
                'branches_count' => $branches->count(),
                'online_branches_count' => $branches->where('connection_status', 'online')->count(),
                'pending_qr_orders_count' => $branches->sum('pending_orders_count'),
                'received_by_cashier_count' => $branches->sum('received_orders_count'),
                'imported_today_count' => $branches->sum('imported_today_count'),
                'rejected_today_count' => $branches->sum('rejected_today_count'),
            ],
            'branches' => $branches,
            'recent_qr_orders' => $recentOrders,
        ]);
    }
}
