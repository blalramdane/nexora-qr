<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Retire client/demo template identities without deleting existing rows.
        if (Schema::hasTable('templates')) {
            foreach ([
                'al-agha' => ['name' => 'Fast Food (Archived)', 'description' => 'Archived legacy template; new menus should use a general platform template.'],
                'al-saqa' => ['name' => 'Restaurant (Archived)', 'description' => 'Archived legacy template; new menus should use a general platform template.'],
                'yum-box' => ['name' => 'Fast Food 3D (Archived)', 'description' => 'Archived legacy template; new menus should use a general platform template.'],
                'de-papa' => ['name' => 'Cafe (Archived)', 'description' => 'Archived legacy template; new menus should use a general platform template.'],
                'panchy-fire' => ['name' => 'Fast Food (Archived)', 'description' => 'Archived legacy template; new menus should use a general platform template.'],
            ] as $key => $display) {
                DB::table('templates')->where('key', $key)->update([
                    'name' => $display['name'],
                    'description' => $display['description'],
                    'is_active' => false,
                    'updated_at' => now(),
                ]);
            }
        }

        // Hide only known demo tenants from public use. Their records are kept
        // intact for audit/recovery and no customer-created records are deleted.
        if (Schema::hasTable('restaurants')) {
            $demoRestaurantIds = DB::table('restaurants')->whereIn('slug', [
                'al-agha-al-zarqa',
                'al-saqa-al-zarqa',
                'de-papa-lounge',
                'yum-box-fast-food',
                'panchy-restaurant',
            ])->pluck('id');

            if ($demoRestaurantIds->isNotEmpty()) {
                DB::table('restaurants')->whereIn('id', $demoRestaurantIds)->update(['is_active' => false, 'updated_at' => now()]);
                if (Schema::hasTable('menus')) {
                    DB::table('menus')->whereIn('restaurant_id', $demoRestaurantIds)->update(['is_published' => false, 'updated_at' => now()]);
                }
            }
        }
    }

    public function down(): void
    {
        // Deliberately do not reactivate retired demo content automatically.
        // Existing records are retained, and activation should be an explicit
        // operational decision rather than a rollback side effect.
    }
};
