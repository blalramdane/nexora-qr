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
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class MenuFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_templates_can_have_versioned_schemas(): void
    {
        $template = Template::create([
            'key' => 'fast-food',
            'name' => 'Fast Food',
            'description' => 'Fast ordering',
        ]);

        $version = $template->versions()->create([
            'version' => 1,
            'schema' => ['components' => ['header', 'product_card']],
            'is_active' => true,
        ]);

        $this->assertSame(1, $template->activeVersion()->firstOrFail()->version);
        $this->assertSame(['components' => ['header', 'product_card']], $version->schema);
    }

    public function test_menu_domain_is_tenant_isolated(): void
    {
        $a = Restaurant::create(['name' => 'A', 'slug' => 'a']);
        $b = Restaurant::create(['name' => 'B', 'slug' => 'b']);

        $template = Template::create(['key' => 'cafe', 'name' => 'Cafe']);

        app(TenantContext::class)->set($a);
        $menuA = Menu::create(['restaurant_id' => $a->id, 'template_id' => $template->id, 'name' => 'A Menu', 'slug' => 'a-menu']);

        app(TenantContext::class)->set($b);
        $menuB = Menu::create(['restaurant_id' => $b->id, 'template_id' => $template->id, 'name' => 'B Menu', 'slug' => 'b-menu']);

        app(TenantContext::class)->set($a);

        $this->assertCount(1, Menu::query()->get());
        $this->assertSame($menuA->id, Menu::query()->first()->id);
        $this->assertNull(Menu::query()->whereKey($menuB->id)->first());
    }

    public function test_products_and_variants_are_isolated_to_menu_tenant(): void
    {
        $restaurant = Restaurant::create(['name' => 'A', 'slug' => 'a']);
        $template = Template::create(['key' => 'fast-food', 'name' => 'Fast Food']);

        app(TenantContext::class)->set($restaurant);

        $menu = Menu::create(['restaurant_id' => $restaurant->id, 'template_id' => $template->id, 'name' => 'Menu', 'slug' => 'menu']);
        $category = MenuCategory::create(['restaurant_id' => $restaurant->id, 'menu_id' => $menu->id, 'name' => 'Burgers', 'slug' => 'burgers']);

        $product = Product::create([
            'restaurant_id' => $restaurant->id,
            'menu_category_id' => $category->id,
            'name' => 'Classic',
            'slug' => 'classic',
            'price' => 120,
        ]);

        $product->variants()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Double',
            'price_delta' => 40,
        ]);

        $loaded = Product::query()->with('variants')->firstOrFail();

        $this->assertSame('Classic', $loaded->name);
        $this->assertCount(1, $loaded->variants);
        $this->assertSame('Double', $loaded->variants->first()->name);
    }

    public function test_owner_can_update_and_delete_a_menu(): void
    {
        $restaurant = Restaurant::create(['name' => 'A', 'slug' => 'a']);
        $user = User::factory()->create();
        $restaurant->users()->attach($user->id, ['role' => 'owner']);

        $template = Template::create(['key' => 'fast-food', 'name' => 'Fast Food']);
        $template->createVersion(['components' => ['header']], true);
        app(TenantContext::class)->set($restaurant);

        $menu = Menu::create([
            'restaurant_id' => $restaurant->id,
            'template_id' => $template->id,
            'name' => 'Old Menu',
            'slug' => 'old-menu',
        ]);

        $this->actingAs($user)
            ->put("/menus/{$menu->id}", [
                'name' => 'New Menu',
                'template_key' => 'fast-food',
            ])
            ->assertRedirect("/menus/{$menu->id}");

        $this->assertDatabaseHas('menus', [
            'id' => $menu->id,
            'name' => 'New Menu',
            'slug' => 'new-menu',
        ]);

        $this->actingAs($user)
            ->delete("/menus/{$menu->id}")
            ->assertRedirect('/menus');

        $this->assertDatabaseMissing('menus', ['id' => $menu->id]);
    }

    public function test_manager_can_manage_menus_but_regular_member_cannot(): void
    {
        $restaurant = Restaurant::create(['name' => 'A', 'slug' => 'a']);
        $manager = User::factory()->create();
        $member = User::factory()->create();
        $restaurant->users()->attach($manager->id, ['role' => 'manager']);
        $restaurant->users()->attach($member->id, ['role' => 'staff']);

        $template = Template::create(['key' => 'cafe', 'name' => 'Cafe']);
        $template->createVersion(['components' => ['header']], true);
        app(TenantContext::class)->set($restaurant);

        $this->actingAs($manager)
            ->post('/menus', ['name' => 'Manager Menu', 'template_key' => 'cafe'])
            ->assertRedirect();

        $this->actingAs($member)
            ->get('/menus')
            ->assertForbidden();
    }

    public function test_creating_menus_with_the_same_name_generates_unique_slugs(): void
    {
        $restaurant = Restaurant::create(['name' => 'A', 'slug' => 'a']);
        $owner = User::factory()->create();
        $restaurant->users()->attach($owner->id, ['role' => 'owner']);

        $template = Template::create(['key' => 'cafe', 'name' => 'Cafe']);
        $template->createVersion(['components' => ['header']], true);
        app(TenantContext::class)->set($restaurant);

        $this->actingAs($owner)
            ->post('/menus', ['name' => 'VIBES', 'template_key' => 'cafe'])
            ->assertRedirect();

        $this->actingAs($owner)
            ->post('/menus', ['name' => 'VIBES', 'template_key' => 'cafe'])
            ->assertRedirect();

        $this->assertDatabaseHas('menus', ['restaurant_id' => $restaurant->id, 'slug' => 'vibes']);
        $this->assertDatabaseHas('menus', ['restaurant_id' => $restaurant->id, 'slug' => 'vibes-2']);
    }

    public function test_menu_update_cannot_cross_tenant_boundary(): void
    {
        $restaurantA = Restaurant::create(['name' => 'A', 'slug' => 'a']);
        $restaurantB = Restaurant::create(['name' => 'B', 'slug' => 'b']);
        $userA = User::factory()->create();
        $restaurantA->users()->attach($userA->id, ['role' => 'owner']);

        $template = Template::create(['key' => 'cafe', 'name' => 'Cafe']);

        app(TenantContext::class)->set($restaurantB);
        $menuB = Menu::create([
            'restaurant_id' => $restaurantB->id,
            'template_id' => $template->id,
            'name' => 'B Menu',
            'slug' => 'b-menu',
        ]);

        app(TenantContext::class)->set($restaurantA);

        $this->actingAs($userA)
            ->put("/menus/{$menuB->id}", [
                'name' => 'Hijacked',
                'template_key' => 'cafe',
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('menus', [
            'id' => $menuB->id,
            'name' => 'B Menu',
        ]);
    }

    public function test_csv_menu_import_reuses_existing_products_variants_and_modifiers(): void
    {
        $restaurant = Restaurant::create(['name' => 'Import Cafe', 'slug' => 'import-cafe']);
        $owner = User::factory()->create();
        $restaurant->users()->attach($owner->id, ['role' => 'owner']);
        $template = Template::create(['key' => 'cafe', 'name' => 'Cafe']);
        $template->createVersion(['components' => ['header']], true);
        app(TenantContext::class)->set($restaurant);
        $menu = Menu::create(['restaurant_id' => $restaurant->id, 'template_id' => $template->id, 'name' => 'Menu', 'slug' => 'menu']);
        $csv = "category,product,price,description,variant,variant_price,variant_delta,modifier,modifier_price,modifier_required\n".
            "Burgers,Classic,100,Beef,Double,150,,Cheese,15,false\n".
            "Burgers,Classic,100,Beef,Single,100,,Sauce,5,false\n";

        foreach ([1, 2] as $run) {
            $this->actingAs($owner)
                ->post("/menus/{$menu->id}/import", ['file' => UploadedFile::fake()->createWithContent('menu.csv', $csv)])
                ->assertRedirect("/menus/{$menu->id}/import");
        }

        $category = MenuCategory::where('menu_id', $menu->id)->where('name', 'Burgers')->firstOrFail();
        $product = Product::where('menu_category_id', $category->id)->where('name', 'Classic')->firstOrFail();
        $this->assertSame(1, MenuCategory::where('menu_id', $menu->id)->count());
        $this->assertSame(1, Product::where('menu_category_id', $category->id)->count());
        $this->assertSame(2, $product->variants()->count());
        $this->assertSame(2, $product->modifiers()->count());
        $this->assertDatabaseHas('products', ['id' => $product->id, 'price' => 100]);
    }
}
