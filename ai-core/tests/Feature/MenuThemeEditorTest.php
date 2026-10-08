<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\Restaurant;
use App\Models\Template;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuThemeEditorTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_update_menu_theme_tokens(): void
    {
        [$restaurant, $user, $menu] = $this->context();

        $this->actingAs($user)->put("/menus/{$menu->id}/theme", [
            'primary' => '#111111',
            'accent' => '#FF8800',
            'background' => '#FFFFFF',
            'foreground' => '#222222',
            'radius' => '2xl',
        ])->assertRedirect("/menus/{$menu->id}");

        $this->assertSame([
            'primary' => '#111111',
            'accent' => '#FF8800',
            'background' => '#FFFFFF',
            'foreground' => '#222222',
            'radius' => '2xl',
        ], $menu->fresh()->theme);
    }

    public function test_theme_rejects_invalid_color_tokens_and_radius(): void
    {
        [, $user, $menu] = $this->context();

        $this->actingAs($user)->put("/menus/{$menu->id}/theme", [
            'primary' => 'red',
            'accent' => '#12345',
            'background' => '#FFFFFF',
            'foreground' => '#222222',
            'radius' => 'huge',
        ])->assertSessionHasErrors(['primary', 'accent', 'radius']);

        $this->assertSame('#111827', $menu->fresh()->theme['primary']);
    }

    public function test_theme_cannot_cross_tenant_boundary(): void
    {
        [, $userA, $menuA] = $this->context('A', 'a');

        $restaurantB = Restaurant::create(['name' => 'B', 'slug' => 'b']);
        app(TenantContext::class)->set($restaurantB);
        $template = Template::firstOrFail();
        $menuB = Menu::create([
            'restaurant_id' => $restaurantB->id,
            'template_id' => $template->id,
            'name' => 'B Menu',
            'slug' => 'b-menu',
            'theme' => ['primary' => '#111827', 'accent' => '#f59e0b', 'background' => '#ffffff', 'foreground' => '#111827', 'radius' => 'xl'],
        ]);

        app(TenantContext::class)->set(Restaurant::findOrFail($menuA->restaurant_id));

        $this->actingAs($userA)->put("/menus/{$menuA->id}/theme", [
            'primary' => '#123456',
            'accent' => '#654321',
            'background' => '#FFFFFF',
            'foreground' => '#222222',
            'radius' => 'lg',
        ])->assertRedirect("/menus/{$menuA->id}");

        $this->assertSame('#123456', $menuA->fresh()->theme['primary']);
        $this->assertSame('#111827', $menuB->fresh()->theme['primary']);
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
            'theme' => ['primary' => '#111827', 'accent' => '#f59e0b', 'background' => '#ffffff', 'foreground' => '#111827', 'radius' => 'xl'],
        ]);

        return [$restaurant, $user, $menu];
    }
}
