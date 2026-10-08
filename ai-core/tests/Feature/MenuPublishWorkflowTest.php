<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuPublishWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_publish_a_ready_menu_and_unpublish_it(): void
    {
        [$user, $restaurant, $menu] = $this->readyMenu();

        $this->actingAs($user)->post(route('menus.publish', $menu))
            ->assertRedirect(route('menus.show', $menu));

        $this->assertTrue($menu->fresh()->is_published);

        $this->actingAs($user)->post(route('menus.unpublish', $menu))
            ->assertRedirect(route('menus.show', $menu));

        $this->assertFalse($menu->fresh()->is_published);
    }

    public function test_publish_requires_an_active_category_and_available_product(): void
    {
        [$user, $restaurant, $menu] = $this->readyMenu();
        MenuCategory::query()->where('menu_id', $menu->id)->update(['is_active' => false]);

        $this->actingAs($user)->post(route('menus.publish', $menu))
            ->assertRedirect(route('menus.show', $menu))
            ->assertStatus(422);

        MenuCategory::query()->where('menu_id', $menu->id)->update(['is_active' => true]);
        Product::query()->where('menu_category_id', MenuCategory::query()->where('menu_id', $menu->id)->value('id'))->update(['is_available' => false]);

        $this->actingAs($user)->post(route('menus.publish', $menu))
            ->assertStatus(422);
    }

    public function test_publish_cannot_cross_tenant_boundary(): void
    {
        [$user, $restaurant, $menu] = $this->readyMenu();
        $other = Restaurant::create(['name' => 'B', 'slug' => 'b']);
        app(\App\Support\Tenancy\TenantContext::class)->set($other);
        $otherMenu = Menu::create(['restaurant_id' => $other->id, 'name' => 'Other', 'slug' => 'other']);
        app(\App\Support\Tenancy\TenantContext::class)->set($restaurant);

        $this->actingAs($user)->post(route('menus.publish', $otherMenu))
            ->assertStatus(404);
    }

    private function readyMenu(): array
    {
        $restaurant = Restaurant::create(['name' => 'A', 'slug' => 'a']);
        $user = User::factory()->create();
        $restaurant->users()->attach($user->id, ['role' => 'manager']);

        $template = Template::create(['key' => 'fast-food', 'name' => 'Fast Food', 'is_active' => true]);
        $version = $template->versions()->create(['version' => 1, 'is_active' => true, 'schema' => ['layout' => 'test']]);

        app(\App\Support\Tenancy\TenantContext::class)->set($restaurant);
        $menu = Menu::create([
            'restaurant_id' => $restaurant->id,
            'template_id' => $template->id,
            'template_version_id' => $version->id,
            'name' => 'Main Menu',
            'slug' => 'main-menu',
            'is_published' => false,
        ]);

        $category = MenuCategory::create([
            'restaurant_id' => $restaurant->id,
            'menu_id' => $menu->id,
            'name' => 'Burgers',
            'slug' => 'burgers',
            'is_active' => true,
        ]);

        Product::create([
            'restaurant_id' => $restaurant->id,
            'menu_category_id' => $category->id,
            'name' => 'Classic Burger',
            'slug' => 'classic-burger',
            'price' => 100,
            'is_available' => true,
        ]);

        return [$user, $restaurant, $menu];
    }
}
