<?php

namespace App\Http\Controllers;

use App\Support\Tenancy\TenantContext;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(TenantContext $tenant): Response
    {
        abort_unless($tenant->check(), 403);

        $restaurant = $tenant->restaurant();
        $todayOrders = $restaurant->orders()->whereDate('created_at', today());
        $todayRevenue = (clone $todayOrders)->whereNotIn('status', ['cancelled'])->sum('total');

        return Inertia::render('Dashboard', [
            'restaurant' => $restaurant->only(['id', 'name', 'slug']),
            'branchCount' => $restaurant->branches()->count(),
            'stats' => [
                'categories' => $restaurant->categories()->count(),
                'products' => $restaurant->categories()->withCount('products')->get()->sum('products_count'),
                'tables' => $restaurant->tables()->where('is_active', true)->count(),
                'today_orders' => $todayOrders->count(),
                'today_revenue' => number_format((float) $todayRevenue, 2, '.', ''),
            ],
        ]);
    }
}