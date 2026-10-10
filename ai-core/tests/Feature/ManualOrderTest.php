<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\Template;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_register_a_manual_order_with_server_calculated_totals_and_payment_details(): void
    {
        [$restaurant, $menu, $product, $user] = $this->makeRestaurant();

        $this->actingAs($user)->post(route('orders.manual.store'), [
            'menu_id' => $menu->id, 'customer_name' => 'عميل تليفون', 'customer_phone' => '01000000000',
            'source' => 'phone', 'fulfillment_type' => 'delivery', 'delivery_address' => 'شارع البحر، دمياط',
            'payment_method' => 'cash_on_delivery', 'payment_status' => 'unpaid',
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])->assertRedirect(route('orders.index'))->assertSessionHas('success');

        $this->assertDatabaseHas('orders', [
            'restaurant_id' => $restaurant->id, 'menu_id' => $menu->id, 'customer_name' => 'عميل تليفون',
            'source' => 'phone', 'payment_method' => 'cash_on_delivery', 'payment_status' => 'unpaid',
            'fulfillment_type' => 'delivery', 'delivery_address' => 'شارع البحر، دمياط',
            'subtotal' => 200, 'delivery_fee' => 25, 'total' => 225, 'items_count' => 2, 'status' => 'new',
        ]);
        $this->assertDatabaseHas('order_items', ['product_id' => $product->id, 'product_name' => 'Classic Burger', 'quantity' => 2, 'unit_price' => 100, 'line_total' => 200]);
        $this->assertDatabaseHas('order_status_histories', ['to_status' => 'new', 'changed_by' => $user->id]);
    }

    public function test_manual_order_rejects_a_product_from_another_restaurant(): void
    {
        [$restaurant, $menu, , $user] = $this->makeRestaurant('Restaurant One', 'restaurant-one');
        [, , $foreignProduct] = $this->makeRestaurant('Restaurant Two', 'restaurant-two');

        $this->actingAs($user)->from('/orders')->post(route('orders.manual.store'), [
            'menu_id' => $menu->id, 'customer_name' => 'Customer', 'customer_phone' => '01000000000',
            'source' => 'manual', 'fulfillment_type' => 'pickup', 'payment_method' => 'cash', 'payment_status' => 'unpaid',
            'items' => [['product_id' => $foreignProduct->id, 'quantity' => 1]],
        ])->assertStatus(422);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_manual_delivery_order_requires_an_address_and_only_managers_or_owners_can_create_orders(): void
    {
        [$restaurant, $menu, $product, $user] = $this->makeRestaurant();
        $payload = [
            'menu_id' => $menu->id, 'customer_name' => 'Customer', 'customer_phone' => '01000000000',
            'source' => 'whatsapp', 'fulfillment_type' => 'delivery', 'payment_method' => 'cash', 'payment_status' => 'unpaid',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ];

        $this->actingAs($user)->from('/orders')->post(route('orders.manual.store'), $payload)->assertSessionHasErrors('delivery_address');
        $this->assertDatabaseCount('orders', 0);

        $unauthorized = User::factory()->create();
        $this->actingAs($unauthorized)->post(route('orders.manual.store'), $payload)->assertForbidden();
        $this->assertDatabaseCount('orders', 0);
    }

    private function makeRestaurant(string $name = 'Manual Restaurant', string $slug = 'manual-restaurant'): array
    {
        $restaurant = Restaurant::create(['name' => $name, 'slug' => $slug, 'is_active' => true, 'delivery_fee' => 25, 'delivery_enabled' => true]);
        app(TenantContext::class)->set($restaurant);
        $user = User::factory()->create();
        $user->restaurants()->attach($restaurant->id, ['role' => 'owner']);
        $template = Template::create(['key' => 'template-'.$slug, 'name' => 'Fast Food', 'is_active' => true]);
        $version = $template->createVersion(['layout' => []], true);
        $menu = Menu::create(['restaurant_id' => $restaurant->id, 'template_id' => $template->id, 'template_version_id' => $version->id, 'name' => 'Menu', 'slug' => 'menu', 'is_published' => true]);
        $category = MenuCategory::create(['restaurant_id' => $restaurant->id, 'menu_id' => $menu->id, 'name' => 'Burgers', 'slug' => 'burgers', 'is_active' => true]);
        $product = Product::create(['restaurant_id' => $restaurant->id, 'menu_category_id' => $category->id, 'name' => 'Classic Burger', 'slug' => 'classic-burger', 'price' => 100, 'is_available' => true]);

        return [$restaurant, $menu, $product, $user];
    }
}
