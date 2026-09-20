<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_create_an_order_from_a_public_menu(): void
    {
        $restaurant = Restaurant::create(['name' => 'Cafe', 'slug' => 'cafe', 'is_active' => true]);
        $category = Category::create(['restaurant_id' => $restaurant->id, 'name' => 'Burgers', 'slug' => 'burgers']);
        $product = Product::create([
            'restaurant_id' => $restaurant->id,
            'category_id' => $category->id,
            'name' => 'Classic',
            'slug' => 'classic',
            'price' => 100,
            'is_available' => true,
        ]);
        $table = RestaurantTable::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Table 1',
            'token' => 'table-token',
            'is_active' => true,
        ]);

        $response = $this->post('/orders', [
            'restaurant_slug' => 'cafe',
            'table_token' => 'table-token',
            'customer_name' => 'Ahmed',
            'items' => [['product_id' => $product->id, 'quantity' => 2, 'modifier_ids' => []]],
            'idempotency_key' => 'test-order-1',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('orders', [
            'restaurant_id' => $restaurant->id,
            'table_id' => $table->id,
            'status' => 'pending',
            'total' => 200,
        ]);
        $this->assertDatabaseHas('order_events', ['event' => 'created', 'to_status' => 'pending']);
    }

    public function test_order_rejects_a_product_from_another_restaurant(): void
    {
        $restaurantA = Restaurant::create(['name' => 'A', 'slug' => 'a', 'is_active' => true]);
        $restaurantB = Restaurant::create(['name' => 'B', 'slug' => 'b', 'is_active' => true]);
        $category = Category::create(['restaurant_id' => $restaurantB->id, 'name' => 'Private', 'slug' => 'private']);
        $product = Product::create(['restaurant_id' => $restaurantB->id, 'category_id' => $category->id, 'name' => 'Private', 'slug' => 'private-product', 'price' => 50, 'is_available' => true]);

        $this->post('/orders', [
            'restaurant_slug' => 'a',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'modifier_ids' => []]],
            'idempotency_key' => 'cross-tenant',
        ])->assertNotFound();
    }

    public function test_modifier_price_and_rules_are_calculated_server_side(): void
    {
        $restaurant = Restaurant::create(['name' => 'Cafe', 'slug' => 'modifier-cafe', 'is_active' => true]);
        $category = Category::create(['restaurant_id' => $restaurant->id, 'name' => 'Pizza', 'slug' => 'pizza']);
        $product = Product::create(['restaurant_id' => $restaurant->id, 'category_id' => $category->id, 'name' => 'Pizza', 'slug' => 'pizza', 'price' => 100, 'is_available' => true]);
        $group = ModifierGroup::create(['restaurant_id' => $restaurant->id, 'name' => 'Sauce', 'min_selections' => 1, 'max_selections' => 1, 'is_required' => true]);
        $modifier = Modifier::create(['modifier_group_id' => $group->id, 'name' => 'Cheese', 'price_delta' => 20, 'is_available' => true]);
        $product->modifierGroups()->attach($group->id);

        $this->post('/orders', [
            'restaurant_slug' => 'modifier-cafe',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'modifier_ids' => [$modifier->id]]],
        ])->assertOk();

        $this->assertDatabaseHas('orders', ['restaurant_id' => $restaurant->id, 'total' => 120]);
    }
}