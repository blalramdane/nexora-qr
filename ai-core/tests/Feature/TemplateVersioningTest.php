<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\Restaurant;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemplateVersioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_template_can_create_sequential_versions_and_activate_one(): void
    {
        $template = Template::create(['key' => 'fast-food', 'name' => 'Fast Food']);

        $v1 = $template->createVersion(['components' => ['header']], true);
        $v2 = $template->createVersion(['components' => ['header', 'product_card']]);

        $this->assertSame(1, $v1->version);
        $this->assertSame(2, $v2->version);
        $this->assertTrue($v1->fresh()->is_active);
        $this->assertFalse($v2->fresh()->is_active);

        $v2->activate();

        $this->assertFalse($v1->fresh()->is_active);
        $this->assertTrue($v2->fresh()->is_active);
        $this->assertSame(2, $template->fresh()->activeVersion()->firstOrFail()->version);
    }

    public function test_version_activation_never_mutates_schema_history(): void
    {
        $template = Template::create(['key' => 'cafe', 'name' => 'Cafe']);
        $v1 = $template->createVersion(['layout' => 'warm'], true);
        $v2 = $template->createVersion(['layout' => 'editorial']);

        $v2->activate();

        $this->assertSame(['layout' => 'warm'], $v1->fresh()->schema);
        $this->assertSame(['layout' => 'editorial'], $v2->fresh()->schema);
    }

    public function test_menu_is_pinned_to_template_version(): void
    {
        $restaurant = Restaurant::create(['name' => 'A', 'slug' => 'a']);
        $user = User::factory()->create();
        $restaurant->users()->attach($user->id, ['role' => 'owner']);
        app(TenantContext::class)->set($restaurant);

        $template = Template::create(['key' => 'fast-food', 'name' => 'Fast Food']);
        $v1 = $template->createVersion(['layout' => 'v1'], true);
        $menu = Menu::create([
            'restaurant_id' => $restaurant->id,
            'template_id' => $template->id,
            'template_version_id' => $v1->id,
            'name' => 'Menu',
            'slug' => 'menu',
        ]);

        $v2 = $template->createVersion(['layout' => 'v2']);
        $v2->activate();

        $this->assertSame($v1->id, $menu->fresh()->template_version_id);
        $this->assertSame(1, $menu->fresh()->templateVersion->version);
        $this->assertSame(2, $template->fresh()->activeVersion->version);
    }

    public function test_menu_template_change_pins_to_new_template_active_version(): void
    {
        $restaurant = Restaurant::create(['name' => 'A', 'slug' => 'a']);
        $user = User::factory()->create();
        $restaurant->users()->attach($user->id, ['role' => 'owner']);
        app(TenantContext::class)->set($restaurant);

        $first = Template::create(['key' => 'fast-food', 'name' => 'Fast Food']);
        $firstVersion = $first->createVersion(['layout' => 'fast'], true);
        $second = Template::create(['key' => 'cafe', 'name' => 'Cafe']);
        $secondVersion = $second->createVersion(['layout' => 'cafe'], true);

        $menu = Menu::create([
            'restaurant_id' => $restaurant->id,
            'template_id' => $first->id,
            'template_version_id' => $firstVersion->id,
            'name' => 'Menu',
            'slug' => 'menu',
        ]);

        $this->actingAs($user)
            ->put("/menus/{$menu->id}", [
                'name' => 'Menu',
                'template_key' => 'cafe',
            ])
            ->assertRedirect("/menus/{$menu->id}");

        $menu = $menu->fresh();
        $this->assertSame($second->id, $menu->template_id);
        $this->assertSame($secondVersion->id, $menu->template_version_id);
    }
}
