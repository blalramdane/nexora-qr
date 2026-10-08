<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\Template;
use App\Models\TemplateVersion;
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
            ->assertStatus(422);

        MenuCategory::query()->where('menu_id', $menu->id)->update(['is_active' => true]);
        Product::query()->where('menu_category_id', MenuCategory::query()->where('menu_id', $menu->id)->value('id'))->update(['is_available' => false]);

        $this->actingAs($user)->post(route('menus.publish', $menu))
            ->assertStatus(422);
    }

    public function test_publish_cannot_cross_tenant_boundary(): void
    {
        [$user, $restaurant, $menu] = $this->readyMenu();
        $other = Restaurant::factory()->create();
        $otherMenu = Menu::factory()->create(['restaurant_id' => $other->id]);

        $this->actingAs($user)->post(route('menus.publish', $otherMenu))
            ->assertStatus(404);
    }

    private function readyMenu(): array
    {
        $restaurant = Restaurant::factory()->create();
        $user = User::factory()->create();
        $restaurant->users()->attach($user->id, ['role' => 'manager']);

        $template = Template::factory()->create(['is_active' => true]);
        $version = TemplateVersion::factory()->create([
            'template_id' => $template->id,
            'version' => 1,
            'is_active' => true,
            'schema' => ['layout' => 'test'],
        ]);

        $menu = Menu::factory()->create([
            'restaurant_id' => $restaurant->id,
            'template_id' => $template->id,
            'template_version_id' => $version->id,
            'is_published' => false,
        ]);

        $category = MenuCategory::factory()->create([
            'restaurant_id' => $restaurant->id,
            'menu_id' => $menu->id,
            'is_active' => true,
        ]);

        Product::factory()->create([
            'restaurant_id' => $restaurant->id,
            'menu_category_id' => $category->id,
            'is_available' => true,
        ]);

        return [$user, $restaurant, $menu];
    }
}
