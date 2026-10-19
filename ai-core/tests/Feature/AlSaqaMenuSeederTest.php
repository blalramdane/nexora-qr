<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\Template;
use Database\Seeders\AlSaqaMenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlSaqaMenuSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_import_reuses_the_existing_saqa_tenant_and_is_idempotent(): void
    {
        $restaurant = Restaurant::create([
            'name' => 'مطعم السقا',
            'slug' => 'al-saqa-al-zarqa',
            'is_active' => false,
            'timezone' => 'Africa/Cairo',
        ]);

        $template = Template::create([
            'key' => 'fast-food',
            'name' => 'Fast Food',
            'is_active' => true,
        ]);
        $template->createVersion(['layout' => 'fast-food'], true);

        $this->seed(AlSaqaMenuSeeder::class);
        $this->seed(AlSaqaMenuSeeder::class);

        $this->assertDatabaseCount('restaurants', 1);
        $this->assertDatabaseHas('restaurants', [
            'id' => $restaurant->id,
            'slug' => 'al-saqa-al-zarqa',
            'is_active' => true,
        ]);
        $this->assertDatabaseCount('menus', 1);
        $this->assertDatabaseHas('menus', [
            'restaurant_id' => $restaurant->id,
            'slug' => 'al-saqa-menu',
            'is_published' => true,
        ]);
        $this->assertDatabaseMissing('restaurants', ['slug' => 'al-saqa']);
        $this->assertDatabaseCount('menu_categories', 8);
        $this->assertDatabaseCount('products', 69);
    }
}
