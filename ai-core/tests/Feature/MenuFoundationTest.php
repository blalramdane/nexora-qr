<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Restaurant;
use App\Models\Template;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
