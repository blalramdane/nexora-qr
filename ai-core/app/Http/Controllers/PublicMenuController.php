<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicMenuController extends Controller
{
    public function show(Request $request, string $restaurantSlug, string $menuSlug): Response
    {
        $restaurant = Restaurant::query()->where('slug', $restaurantSlug)->where('is_active', true)->firstOrFail();
        app(TenantContext::class)->set($restaurant);

        $menu = Menu::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('slug', $menuSlug)
            ->where('is_published', true)
            ->with([
                'template',
                'templateVersion',
                'categories' => fn ($query) => $query->where('is_active', true),
                'categories.products' => fn ($query) => $query->where('is_available', true),
                'categories.products.variants' => fn ($query) => $query->where('is_active', true),
                'categories.products.modifiers' => fn ($query) => $query->where('is_active', true),
            ])
            ->firstOrFail();

        // Menus are pinned to an immutable template version. It may no longer be
        // the active version after a template update, but published menus must
        // continue rendering the version they were created with.
        abort_unless($menu->templateVersion, 404);

        $tableToken = trim((string) $request->query('table', ''));
        $tableContext = null;
        if ($tableToken !== '') {
            abort_if(strlen($tableToken) > 128, 404);
            $table = RestaurantTable::query()
                ->where('restaurant_id', $restaurant->id)
                ->where('qr_token_hash', hash('sha256', $tableToken))
                ->where('is_active', true)
                ->firstOrFail();
            $tableContext = ['name' => $table->name, 'token' => $tableToken];
        }

        return Inertia::render('Menu/Public', [
            'restaurant' => [
                'id' => $restaurant->id,
                'name' => $restaurant->name,
                'slug' => $restaurant->slug,
                'phone' => $restaurant->phone,
                'whatsapp_phone' => $restaurant->whatsapp_phone,
                'logo_path' => $restaurant->logo_path,
                'delivery_fee' => $restaurant->delivery_fee ?? '0.00',
                'delivery_areas' => $restaurant->delivery_areas ?? [],
                'delivery_enabled' => $restaurant->delivery_enabled ?? true,
                'pickup_enabled' => $restaurant->pickup_enabled ?? true,
            ],
            'menu' => $menu,
            'template' => $menu->template,
            'tableContext' => $tableContext,
        ]);
    }
}
