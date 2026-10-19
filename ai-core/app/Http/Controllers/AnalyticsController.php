<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function overview(Request $request): JsonResponse
    {
        $data = $request->validate(['days' => ['sometimes', 'integer', 'in:7,30']]);
        $days = (int) ($data['days'] ?? 7);
        $restaurant = app(TenantContext::class)->restaurant();
        $restaurantId = (int) $restaurant->id;
        $to = now();
        $from = $to->copy()->startOfDay()->subDays($days - 1);

        $base = Order::query()
            ->where('restaurant_id', $restaurantId)
            ->whereBetween('created_at', [$from, $to]);

        $orders = (clone $base)->count();
        $completedOrders = (clone $base)->where('status', 'completed')->count();
        $revenue = round((float) (clone $base)->where('status', 'completed')->sum('total'), 2);
        $averageOrder = $completedOrders > 0 ? round($revenue / $completedOrders, 2) : 0.0;

        $daily = (clone $base)
            ->selectRaw('DATE(created_at) AS day, COUNT(*) AS orders_count, SUM(CASE WHEN status = ? THEN total ELSE 0 END) AS revenue', ['completed'])
            ->groupByRaw('DATE(created_at)')
            ->get()->keyBy('day');

        $trend = [];
        for ($date = $from->copy()->startOfDay(); $date->lte($to->copy()->startOfDay()); $date->addDay()) {
            $row = $daily->get($date->toDateString());
            $trend[] = [
                'date' => $date->toDateString(),
                'orders' => (int) ($row->orders_count ?? 0),
                'revenue' => round((float) ($row->revenue ?? 0), 2),
            ];
        }

        $statusBreakdown = (clone $base)
            ->select('status')->selectRaw('COUNT(*) AS count')
            ->groupBy('status')->orderByDesc('count')->get()
            ->map(fn ($row) => ['status' => (string) $row->status, 'count' => (int) $row->count])
            ->values();

        $topItems = DB::table('order_items AS oi')
            ->join('orders AS o', 'o.id', '=', 'oi.order_id')
            ->where('o.restaurant_id', $restaurantId)
            ->where('o.status', 'completed')
            ->whereBetween('o.created_at', [$from, $to])
            ->select('oi.product_name')
            ->selectRaw('SUM(oi.quantity) AS quantity, SUM(oi.line_total) AS sales')
            ->groupBy('oi.product_name')
            ->orderByDesc('quantity')->orderByDesc('sales')->limit(5)->get()
            ->map(fn ($row) => [
                'name' => (string) $row->product_name,
                'quantity' => (int) $row->quantity,
                'sales' => round((float) $row->sales, 2),
            ])->values();

        return response()->json([
            'range' => ['days' => $days, 'from' => $from->toDateString(), 'to' => $to->toDateString()],
            'orders' => $orders,
            'completed_orders' => $completedOrders,
            'revenue' => $revenue,
            'aov' => $averageOrder,
            'completion_rate' => $orders > 0 ? round(($completedOrders / $orders) * 100, 1) : 0.0,
            'trend' => $trend,
            'top_items' => $topItems,
            'status_breakdown' => $statusBreakdown,
        ]);
    }
}
