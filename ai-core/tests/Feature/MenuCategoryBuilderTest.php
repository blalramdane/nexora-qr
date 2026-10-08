<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\Restaurant;
use App\Models\Template;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuCategoryBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_update_reorder_and_delete_categories(): void
    {
        [$restaurant, $user, $menu] = $this->menuContext();

        $response = $this->actingAs($user)->post("/menus/{$menu->id}/categories", [
            'name' => 'Burgers',
            'description' => 'Main burgers',
            'is_active' => true,
        ]);

        $response->assertRedirect("/menus/{$menu->id}");
        $category = MenuCategory::query()->firstOrFail();

        $this->assertSame('burgers', $category->slug);
        $this->assertSame(1, $category->sort_order);
        $this->assertTrue($category->is_active);

        $this->actingAs($user)
            ->put("/menus/{$menu->id}/categories/{$category->id}", [
                'name' => 'Special Burgers',
                'description' => 'Updated',
                'is_active' => false,
                'sort_order' => 0,
            ])
            ->assertRedirect("/menus/{$menu->id}");

        $this->assertDatabaseHas('menu_categories', [
            'id' => $category->id,
            'name' => 'Special Burgers',
            'slug' => 'special-burgers',
            'is_active' => false,
            'sort_order' => 0,
        ]);

        $second = MenuCategory::create([
            'restaurant_id' => $restaurant->id,
            'menu_id' => $menu->id,
            'name' => 'Drinks',
            'slug' => 'drinks',
            'sort_order' => 1,
        ]);

        $this->actingAs($user)
            ->post("/menus/{$menu->id}/categories/reorder", [
                'category_ids' => [$second->id, $category->id],
            ])
            ->assertRedirect("/menus/{$menu->id}");

        $this->assertSame(0, $second->fresh()->sort_order);
        $this->assertSame(1, $category->fresh()->sort_order);

        $this->actingAs($user)
            ->delete("/menus/{$menu->id}/categories/{$category->id}")
            ->assertRedirect("/menus/{$menu->id}");

        $this->assertDatabaseMissing('menu_categories', ['id' => $category->id]);
    }

    public function test_category_slug_is_unique_per_menu(): void
    {
        [, $user, $menu] = $this->menuContext();

        MenuCategory::create([
            'menu_id' => $menu->id,
            'name' => 'Burgers',
            'slug' => 'burgers',
            'sort_order' => 0,
        ]);

        $this->actingAs($user)->post("/menus/{$menu->id}/categories", [
            'name' => 'Burgers',
        ])->assertRedirect("/menus/{$menu->id}");

        $this->assertDatabaseHas('menu_categories', ['slug' => 'burgers-2']);
    }

    public function test_category_management_cannot_cross_tenant_boundary(): void
    {
        [$restaurantA, $userA, $menuA] = $this->menuContext('A', 'a');
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

        app(TenantContext::class)->set($restaurantA);

        $this->actingAs($userA)
            ->put("/menus/{$menuB->id}/categories/{$categoryB->id}", [
                'name' => 'Hijacked',
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('menu_categories', [
            'id' => $categoryB->id,
            'name' => 'Burgers',
        ]);
    }

    private function menuContext(string $name = 'A', string $slug = 'a'): array
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

        return [$restaurant, $user, $menu];
    }
}
