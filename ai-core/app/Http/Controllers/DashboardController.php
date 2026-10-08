<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $restaurant = app(TenantContext::class)->restaurant()->loadCount('branches');

        $menus = Menu::query()
            ->with('template:id,key,name')
            ->latest('id')
            ->limit(5)
            ->get(['id', 'name', 'slug', 'template_id', 'is_published', 'updated_at']);

        return Inertia::render('Dashboard', [
            'restaurant' => [
                'id' => $restaurant->id,
                'name' => $restaurant->name,
                'slug' => $restaurant->slug,
                'branches_count' => $restaurant->branches_count,
            ],
            'stats' => [
                'menus' => Menu::query()->count(),
                'published_menus' => Menu::query()->where('is_published', true)->count(),
                'products' => Product::query()->count(),
            ],
            'menus' => $menus->map(fn (Menu $menu) => [
                'id' => $menu->id,
                'name' => $menu->name,
                'slug' => $menu->slug,
                'is_published' => $menu->is_published,
                'updated_at' => $menu->updated_at?->toIso8601String(),
                'template' => $menu->template ? [
                    'key' => $menu->template->key,
                    'name' => $menu->template->name,
                ] : null,
            ])->values(),
        ]);
    }
}
