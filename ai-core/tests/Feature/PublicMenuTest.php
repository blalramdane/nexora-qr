<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_menu_only_shows_active_categories_and_available_products(): void
    {
        $restaurant = Restaurant::create([
            'name' => 'Public Cafe',
            'slug' => 'public-cafe',
            'is_active' => true,
        ]);

        $visible = Category::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Burgers',
            'slug' => 'burgers',
            'is_active' => true,
        ]);

        Product::create([
            'restaurant_id' => $restaurant->id,
            'category_id' => $visible->id,
            'name' => 'Classic',
            'slug' => 'classic',
            'price' => 100,
            'is_available' => true,
        ]);

        Product::create([
            'restaurant_id' => $restaurant->id,
            'category_id' => $visible->id,
            'name' => 'Hidden',
            'slug' => 'hidden',
            'price' => 90,
            'is_available' => false,
        ]);

        Category::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Disabled',
            'slug' => 'disabled',
            'is_active' => false,
        ]);

        $this->get('/m/public-cafe')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PublicMenu/Show')
                ->where('categories.0.name', 'Burgers')
                ->has('categories.0.products', 1)
                ->where('categories.0.products.0.name', 'Classic')
            );
    }
}