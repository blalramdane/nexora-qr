<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('restaurants') || ! Schema::hasTable('menus') || ! Schema::hasTable('templates')) {
            return;
        }

        $restaurant = DB::table('restaurants')->where('slug', 'al-saqa-al-zarqa')->first();

        // Fresh installations intentionally do not seed client/demo tenants.
        // This repair applies only when the existing Saqa tenant is present.
        if (! $restaurant) {
            return;
        }

        $menu = DB::table('menus')
            ->where('restaurant_id', $restaurant->id)
            ->where('slug', 'al-saqa-menu')
            ->first();

        if (! $menu) {
            return;
        }

        $activeCategoryIds = DB::table('menu_categories')
            ->where('menu_id', $menu->id)
            ->where('is_active', true)
            ->pluck('id');

        $availableProducts = $activeCategoryIds->isEmpty() ? 0 : DB::table('products')
            ->whereIn('menu_category_id', $activeCategoryIds)
            ->where('is_available', true)
            ->count();

        if ($availableProducts < 1) {
            throw new \RuntimeException('Refusing to republish the existing Al Saqa menu because it has no active category with an available product.');
        }

        DB::transaction(function () use ($restaurant, $menu): void {
            DB::table('restaurants')->where('id', $restaurant->id)->update([
                'is_active' => true,
                'updated_at' => now(),
            ]);

            if ($menu->template_id) {
                DB::table('templates')->where('id', $menu->template_id)->where('key', 'al-saqa')->update([
                    'name' => 'مطعم السقا',
                    'description' => 'قالب منيو مطعم السقا، بتجربة RTL متوافقة مع NEXORA QR.',
                    'is_active' => true,
                    'updated_at' => now(),
                ]);
            }

            DB::table('menus')->where('id', $menu->id)->update([
                'is_published' => true,
                'published_at' => $menu->published_at ?: now(),
                'updated_at' => now(),
            ]);

            if (Schema::hasTable('local_businesses')) {
                DB::table('local_businesses')->where('restaurant_id', $restaurant->id)->update([
                    'menu_status' => 'available',
                    'menu_url' => rtrim((string) config('app.url'), '/').'/r/al-saqa-al-zarqa/menu/al-saqa-menu',
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        // The customer's public menu is operational data. Rolling back code
        // must not silently disable it again.
    }
};
