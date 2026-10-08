<?php

namespace Tests\\Feature;

use App\\Models\\Menu;
use App\\Models\\MenuCategory;
use App\\Models\\Modifier;
use App\\Models\\Product;
use App\\Models\\Restaurant;
use App\\Models\\Template;
use App\\Models\\User;
use App\\Support\\Tenancy\\TenantContext;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Tests\\TestCase;

class ModifierBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_update_reorder_toggle_and_delete_modifiers(): void
    {
        [$restaurant, $user, $menu, $category, $product] = $this->context();

        $this->actingAs($user)->post($this->url($menu, $category, $product), [
            'name' => 'Extra Cheese',
            'price_delta' => 25,
            'is_required' => true,
            'is_active' => true,
        ])->assertRedirect("/menus/{$menu->id}");

        $first = Modifier::query()->firstOrFail();
        $this->assertSame('25.00', $first->price_delta);
        $this->assertTrue($first->is_required);
        $this->assertTrue($first->is_active);
        $this->assertSame(0, $first->sort_order);

        $this->actingAs($user)->post($this->url($menu, $category, $product), [
            'name' => 'Jalapeno',
            'price_delta' => -5,
        ])->assertRedirect("/menus/{$menu->id}");

        $second = Modifier::query()->where('name', 'Jalapeno')->firstOrFail();
        $this->assertSame('-5.00', $second->price_delta);
        $this->assertFalse($second->is_required);
        $this->assertSame(1, $second->sort_order);

        $this->actingAs($user)->put($this->url($menu, $category, $product, $first), [
            'name' => 'Extra Cheese',
            'price_delta' => 30,
            'is_required' => false,
            'is_active' => false,
        ])->assertRedirect("/menus/{$menu->id}");

        $this->assertDatabaseHas('modifiers', [
            'id' => $first->id,
            'price_delta' => '30.00',
            'is_required' => 0,
            'is_active' => 0,
            'sort_order' => 0,
        ]);

        $this->actingAs($user)->post($this->url($menu, $category, $product) . '/reorder', [
            'modifier_ids' => [$second->id, $first->id],
        ])->assertRedirect("/menus/{$menu->id}");

        $this->assertSame(0, $second->fresh()->sort_order);
        $this->assertSame(1, $first->fresh()->sort_order);

        $this->actingAs($user)->delete($this->url($menu, $category, $product, $first))
            ->assertRedirect("/menus/{$menu->id}");

        $this->assertDatabaseMissing('modifiers', ['id' => $first->id]);
    }

    public function test_modifier_requires_a_valid_price_delta(): void
    {
        [, $user, $menu, $category, $product] = $this->context();

        $this->actingAs($user)->post($this->url($menu, $category, $product), [
            'name' => 'Missing Price',
        ])->assertStatus(422);

        $this->actingAs($user)->post($this->url($menu, $category, $product), [
            'name' => 'Negative Too Large',
            'price_delta' => -100000000,
        ])->assertStatus(422);

        $this->assertDatabaseCount('modifiers', 0);
    }

    public function test_modifier_reorder_requires_exact_product_membership(): void
    {
        [, $user, $menu, $category, $product] = $this->context();

        $first = Modifier::create(['product_id' => $product->id, 'name' => 'First', 'price_delta' => 10, 'sort_order' => 0]);
        $second = Modifier::create(['product_id' => $product->id, 'name' => 'Second', 'price_delta' => 20, 'sort_order' => 1]);

        $this->actingAs($user)->post($this->url($menu, $category, $product) . '/reorder', [
            'modifier_ids' => [$first->id],
        ])->assertStatus(422);

        $this->assertSame(0, $first->fresh()->sort_order);
        $this->assertSame(1, $second->fresh()->sort_order);
    }

    public function test_modifier_cannot_cross_product_or_tenant_boundary(): void
    {
        [$restaurantA, $userA, $menuA, $categoryA, $productA] = $this->context('A', 'a');

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
        $modifierB = Modifier::create([
            'restaurant_id' => $restaurantB->id,
            'product_id' => $productB->id,
            'name' => 'B Modifier',
            'price_delta' => 10,
        ]);

        app(TenantContext::class)->set($restaurantA);

        $this->actingAs($userA)
            ->put($this->url($menuA, $categoryA, $productA, $modifierB), [
                'name' => 'Hijacked',
                'price_delta' => 1,
            ])->assertNotFound();

        $this->assertDatabaseHas('modifiers', ['id' => $modifierB->id, 'name' => 'B Modifier']);
    }

    private function url(Menu $menu, MenuCategory $category, Product $product, ?Modifier $modifier = null): string
    {
        $url = "/menus/{$menu->id}/categories/{$category->id}/products/{$product->id}/modifiers";

        return $modifier ? $url . "/{$modifier->id}" : $url;
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
        $product = Product::create([
            'restaurant_id' => $restaurant->id,
            'menu_category_id' => $category->id,
            'name' => 'Classic',
            'slug' => 'classic',
            'price' => 120,
        ]);

        return [$restaurant, $user, $menu, $category, $product];
    }
}
