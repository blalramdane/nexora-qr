<?php

namespace App\Services;

use App\Models\LocalBusiness;
use App\Models\Restaurant;
use Illuminate\Support\Str;

class SyncRestaurantToLocalDirectory
{
    public function sync(Restaurant $restaurant): LocalBusiness
    {
        $menu = $restaurant->menus()->where('is_published', true)->latest('published_at')->latest('id')->first();
        $business = LocalBusiness::query()->firstOrNew(['restaurant_id' => $restaurant->id]);

        if (! $business->exists) {
            $business->slug = $this->uniqueSlug($restaurant->name, $restaurant->id);
            $business->listing_status = 'draft';
            $business->source = 'nexora_menu';
            $business->lead_status = 'needs_verification';
            $business->menu_rights_status = 'not_checked';
        }

        $business->fill([
            'name' => $restaurant->name,
            'city' => $business->city ?: 'دمياط',
            'category' => $business->category ?: 'مطعم',
            'phone' => $restaurant->phone ?: $restaurant->whatsapp_phone,
            'website_url' => $restaurant->website_url,
            'menu_url' => $menu ? route('public.menu.show', [$restaurant->slug, $menu->slug]) : null,
            'menu_status' => $menu ? 'available' : 'unknown',
            'listing_status' => $menu ? 'published' : ($business->listing_status === 'published' ? 'draft' : $business->listing_status),
            'source_url' => $menu ? route('public.menu.show', [$restaurant->slug, $menu->slug]) : null,
            'source_checked_at' => now(),
        ]);

        $business->save();

        return $business;
    }

    private function uniqueSlug(string $name, int $restaurantId): string
    {
        $base = Str::slug($name) ?: 'nexora-restaurant';
        $slug = $base.'-'.$restaurantId;
        $suffix = 2;
        while (LocalBusiness::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$restaurantId.'-'.$suffix++;
        }

        return $slug;
    }
}
