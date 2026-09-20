<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_and_create_menu_items_for_active_restaurant(): void
    {
        $user = User::create([
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => Hash::make('password'),
        ]);

        $restaurant = Restaurant::create([
            'name' => 'Nexora Cafe',
            'slug' => 'nexora-cafe',
            'is_active' => true,
        ]);

        $user->restaurants()->attach($restaurant->id, [
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->actingAs($user)->withSession([
            'active_restaurant_id' => $restaurant->id,
        ]);

        $this->get('/menu')->assertOk();
        $this->post('/menu/categories', ['name' => 'Burgers'])->assertRedirect();

        $category = Category::where('restaurant_id', $restaurant->id)->firstOrFail();

        $this->post('/menu/products', [
            'category_id' => $category->id,
            'name' => 'Classic Burger',
            'price' => 120,
        ])->assertRedirect();

        $this->assertDatabaseHas('products', [
            'restaurant_id' => $restaurant->id,
            'category_id' => $category->id,
            'name' => 'Classic Burger',
        ]);
    }

    public function test_user_cannot_create_product_in_another_restaurants_category(): void
    {
        $user = User::create([
            'name' => 'Owner',
            'email' => 'owner2@example.com',
            'password' => Hash::make('password'),
        ]);

        $restaurant = Restaurant::create(['name' => 'A', 'slug' => 'a', 'is_active' => true]);
        $other = Restaurant::create(['name' => 'B', 'slug' => 'b', 'is_active' => true]);

        $user->restaurants()->attach($restaurant->id, ['role' => 'owner', 'is_active' => true]);

        $category = Category::create([
            'restaurant_id' => $other->id,
            'name' => 'Private',
            'slug' => 'private',
        ]);

        $this->actingAs($user)->withSession(['active_restaurant_id' => $restaurant->id]);

        $this->post('/menu/products', [
            'category_id' => $category->id,
            'name' => 'Should Not Cross Tenant',
            'price' => 50,
        ])->assertNotFound();

        $this->assertDatabaseMissing('products', ['name' => 'Should Not Cross Tenant']);
    }
}