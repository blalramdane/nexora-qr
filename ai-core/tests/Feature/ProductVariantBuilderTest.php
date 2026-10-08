<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Restaurant;
use App\Models\Template;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductVariantBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_fixed_and_delta_variants_update_reorder_toggle_and_delete(): void
    {
        [$restaurant, $user, $menu, $category, $product] = $this->context();

        $this->actingAs($user)->post($this->url($menu, $category, $product), [
            'name' => 'Large',
            'pricing_mode' => 'fixed',
            'price' => 180,
            'is_active' => true,
        ])->assertRedirect("/menus/{$menu->id}");

        $fixed = ProductVariant::query()->firstOrFail();
        $this->assertSame('180.00', $fixed->price);
        $this->assertSame('0.00', $fixed->price_delta);
        $this->assertTrue($fixed->is_active);
        $this->assertSame(0, $fixed->sort_order);

        $this->actingAs($user)->post($this->url($menu, $category, $product), [
            'name' => 'Extra Cheese',
            'pricing_mode' => 'delta',
            'price_delta' => 25,
        ])->assertRedirect("/menus/{$menu->id}");

        $delta = ProductVariant::query()->where('name', 'Extra Cheese')->firstOrFail();
        $this->assertNull($delta->price);
        $this->assertSame('25.00', $delta->price_delta);
        $this->assertSame(1, $delta->sort_order);

        $this->actingAs($user)->put($this->url($menu, $category, $product, $fixed), [
            'name' => 'XL',
            'pricing_mode' => 'delta',
            'price_delta' => -10,
            'is_active' => false,
        ])->assertRedirect("/menus/{$menu->id}");

        $this->assertDatabaseHas('product_variants', [
            'id' => $fixed->id,
            'name' => 'XL',
            'price' => null,
            'price_delta' => '-10.00',
            'is_active' => 0,
            'sort_order' => 0,
        ]);

        $this->actingAs($user)->post($this->url($menu, $category, $product) . '/reorder', [
            'variant_ids' => [$delta->id, $fixed->id],
        ])->assertRedirect("/menus/{$menu->id}");

        $this->assertSame(0, $delta->fresh()->sort_order);
        $this->assertSame(1, $fixed->fresh()->sort_order);

        $this->actingAs($user)
            ->delete($this->url($menu, $category, $product, $fixed))
            ->assertRedirect("/menus/{$menu->id}");

        $this->assertDatabaseMissing('product_variants', ['id' => $fixed->id]);
    }

    public function test_variant_requires_price_for_fixed_and_delta_for_delta_mode(): void
    {
        [, $user, $menu, $category, $product] = $this->context();

        $this->actingAs($user)->post($this->url($menu, $category, $product), [
            'name' => 'Missing Fixed Price',
            'pricing_mode' => 'fixed',
        ])->assertStatus(422);

        $this->actingAs($user)->post($this->url($menu, $category, $product), [
            'name' => 'Missing Delta',
            'pricing_mode' => 'delta',
        ])->assertStatus(422);

        $this->assertDatabaseCount('product_variants', 0);
    }

    public function test_variant_reorder_requires_exact_product_membership(): void
    {
        [, $user, $menu, $category, $product] = $this->context();

        $first = ProductVariant::create([
            'product_id' => $product->id,
            'name' => 'Small',
            'price' => 100,
            'sort_order' => 0,
        ]);
        $second = ProductVariant::create([
            'product_id' => $product->id,
            'name' => 'Large',
            'price' => 150,
            'sort_order' => 1,
        ]);

        $this->actingAs($user)->post($this->url($menu, $category, $product) . '/reorder', [
            'variant_ids' => [$first->id],
        ])->assertStatus(422);

        $this->assertSame(0, $first->fresh()->sort_order);
        $this->assertSame(1, $second->fresh()->sort_order);
    }

    public function test_variant_cannot_cross_product_or_tenant_boundary(): void
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
        $variantB = ProductVariant::create([
            'restaurant_id' => $restaurantB->id,
            'product_id' => $productB->id,
            'name' => 'B Variant',
            'price' => 100,
        ]);

        app(TenantContext::class)->set($restaurantA);

        $this->actingAs($userA)
            ->put($this->url($menuA, $categoryA, $productA, $variantB), [
                'name' => 'Hijacked',
                'pricing_mode' => 'fixed',
                'price' => 1,
            ])->assertNotFound();

        $this->assertDatabaseHas('product_variants', ['id' => $variantB->id, 'name' => 'B Variant']);
    }

    private function url(Menu $menu, MenuCategory $category, Product $product, ?ProductVariant $variant = null): string
    {
        $url = "/menus/{$menu->id}/categories/{$category->id}/products/{$product->id}/variants";

        return $variant ? $url . "/{$variant->id}" : $url;
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
