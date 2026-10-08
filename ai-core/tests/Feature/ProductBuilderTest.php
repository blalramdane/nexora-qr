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

class ProductBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_update_reorder_and_delete_products(): void
    {
        [$restaurant, $user, $menu, $category] = $this->context();

        $this->actingAs($user)->post("/menus/{$menu->id}/categories/{$category->id}/products", [
            'name' => 'Classic Burger',
            'description' => 'Beef burger',
            'price' => 125.50,
            'is_available' => true,
            'is_featured' => true,
        ])->assertRedirect("/menus/{$menu->id}");

        $product = Product::query()->firstOrFail();
        $this->assertSame('classic-burger', $product->slug);
        $this->assertSame('125.50', $product->price);
        $this->assertTrue($product->is_available);
        $this->assertTrue($product->is_featured);

        $this->actingAs($user)->put("/menus/{$menu->id}/categories/{$category->id}/products/{$product->id}", [
            'name' => 'Classic Double Burger',
            'description' => 'Updated',
            'price' => 175,
            'is_available' => false,
            'is_featured' => false,
            'image_path' => 'https://example.com/burger.jpg',
        ])->assertRedirect("/menus/{$menu->id}");

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Classic Double Burger',
            'slug' => 'classic-double-burger',
            'price' => '175.00',
            'is_available' => 0,
            'is_featured' => 0,
            'image_path' => 'https://example.com/burger.jpg',
        ]);

        $second = Product::create([
            'restaurant_id' => $restaurant->id,
            'menu_category_id' => $category->id,
            'name' => 'Fries',
            'slug' => 'fries',
            'price' => 50,
            'sort_order' => 1,
        ]);

        $this->actingAs($user)->post("/menus/{$menu->id}/categories/{$category->id}/products/reorder", [
            'product_ids' => [$second->id, $product->id],
        ])->assertRedirect("/menus/{$menu->id}");

        $this->assertSame(0, $second->fresh()->sort_order);
        $this->assertSame(1, $product->fresh()->sort_order);

        $this->actingAs($user)
            ->delete("/menus/{$menu->id}/categories/{$category->id}/products/{$product->id}")
            ->assertRedirect("/menus/{$menu->id}");

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_product_slug_is_unique_per_category(): void
    {
        [, $user, $menu, $category] = $this->context();

        Product::create([
            'menu_category_id' => $category->id,
            'name' => 'Burger',
            'slug' => 'burger',
            'price' => 100,
        ]);

        $this->actingAs($user)->post("/menus/{$menu->id}/categories/{$category->id}/products", [
            'name' => 'Burger',
            'price' => 110,
        ])->assertRedirect("/menus/{$menu->id}");

        $this->assertDatabaseHas('products', ['slug' => 'burger-2']);
    }

    public function test_product_cannot_cross_menu_category_or_tenant_boundary(): void
    {
        [$restaurantA, $userA, $menuA, $categoryA] = $this->context('A', 'a');
        $restaurantB = Restaurant::create(['name' => 'B', 'slug' => 'b']);
        app(TenantContext::class)->set($restaurantB);
        $template = Template::firstOrFail();
        $menuB = Menu::create([
            'restaurant_id' => $restaurantB->id,
            'template_id' => $template->id,
            'name' => 'B Menu',
            'slug' => 'b-menu',
        ]);
        $categoryB = MenuCategory::create([
            'restaurant_id' => $restaurantB->id,
            'menu_id' => $menuB->id,
            'name' => 'Burgers',
            'slug' => 'burgers',
        ]);
        $productB = Product::create([
            'restaurant_id' => $restaurantB->id,
            'menu_category_id' => $categoryB->id,
            'name' => 'B Product',
            'slug' => 'b-product',
            'price' => 90,
        ]);

        app(TenantContext::class)->set($restaurantA);

        $this->actingAs($userA)
            ->put("/menus/{$menuA->id}/categories/{$categoryA->id}/products/{$productB->id}", [
                'name' => 'Hijacked',
                'price' => 1,
            ])->assertNotFound();

        $this->assertDatabaseHas('products', ['id' => $productB->id, 'name' => 'B Product']);
    }

    public function test_product_validation_rejects_negative_price(): void
    {
        [, $user, $menu, $category] = $this->context();

        $this->actingAs($user)
            ->post("/menus/{$menu->id}/categories/{$category->id}/products", [
                'name' => 'Bad Product',
                'price' => -1,
            ])->assertSessionHasErrors('price');
    }

    private function context(string $name = 'A', string $slug = 'a'): array
    {
        $restaurant = Restaurant::create(['name' => $name, 'slug' => $slug]);
        $user = User::factory()->create();
        $restaurant->users()->attach($user->id, ['role' => 'owner']);
        $template = Template::create(['key' => 'fast-food', 'name' => 'Fast Food']);
        app(TenantContext::class)->set($restaurant);
        $menu = Menu::create([
            'restaurant_id' => $restaurant->id,
            'template_id' => $template->id,
            'name' => 'Menu',
            'slug' => 'menu',
        ]);
        $category = MenuCategory::create([
            'restaurant_id' => $restaurant->id,
            'menu_id' => $menu->id,
            'name' => 'Burgers',
            'slug' => 'burgers',
        ]);

        return [$restaurant, $user, $menu, $category];
    }
}
