<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use App\Models\Restaurant;
use App\Support\Tenancy\TenantContext;
use Inertia\Inertia;
use Inertia\Response;

class PublicMenuController extends Controller
{
    public function show(string $restaurantSlug, string $menuSlug): Response
    {
        $restaurant = Restaurant::query()->where('slug', $restaurantSlug)->where('is_active', true)->firstOrFail();
        app(TenantContext::class)->set($restaurant);

        $menu = Menu::query()
            ->where('slug', $menuSlug)
            ->where('is_published', true)
            ->with(['template.activeVersion', 'categories.products.variants'])
            ->firstOrFail();

        return Inertia::render('Menu/Public', [
            'restaurant' => $restaurant,
            'menu' => $menu,
            'template' => $menu->template,
        ]);
    }
}
