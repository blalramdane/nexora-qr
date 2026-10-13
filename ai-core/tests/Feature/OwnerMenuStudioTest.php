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
use Illuminate\Http\UploadedFile;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OwnerMenuStudioTest extends TestCase
{
    use RefreshDatabase;

    public function test_restaurant_owner_can_open_owner_studio_and_manage_their_menu(): void
    {
        [$user, $restaurant, $menu, $category, $product] = $this->ownedMenu('Saqa Test');
        app(TenantContext::class)->clear();

        $this->actingAs($user)->get('/owner/menus')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Owner/MenuStudio')
                ->where('platformOwner', false)
                ->has('restaurants', 1)
                ->where('restaurants.0.id', $restaurant->id)
                ->where('restaurants.0.menus.0.id', $menu->id)
                ->where('restaurants.0.menus.0.categories_count', 1)
                ->where('restaurants.0.menus.0.products_count', 1));

        app(TenantContext::class)->clear();
        $this->actingAs($user)->get('/owner/menus/'.$menu->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Menu/Builder')
                ->where('managementBase', '/owner/menus/'.$menu->id)
                ->where('menusIndexUrl', route('owner.menus.index'))
                ->where('restaurantName', $restaurant->name)
                ->where('menu.id', $menu->id));

        app(TenantContext::class)->clear();
        $this->actingAs($user)->put('/owner/menus/'.$menu->id.'/categories/'.$category->id.'/products/'.$product->id, [
            'name' => 'حواوشي مميز',
            'description' => 'بالجبنة والسجق',
            'price' => 65,
            'is_available' => true,
            'is_featured' => true,
        ])->assertRedirect(route('owner.menus.show', $menu));

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'حواوشي مميز',
            'description' => 'بالجبنة والسجق',
            'price' => 65,
            'is_featured' => true,
        ]);

        app(TenantContext::class)->clear();
        $this->get(route('public.menu.show', [$restaurant->slug, $menu->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Menu/Public')
                ->where('menu.categories.0.products.0.name', 'حواوشي مميز')
                ->where('menu.categories.0.products.0.price', '65.00'));
    }

    public function test_owner_can_upload_a_product_image_through_the_owner_menu_route(): void
    {
        Storage::fake('public');
        [$user, $restaurant, $menu, $category, $product] = $this->ownedMenu('Image Test');
        app(TenantContext::class)->clear();

        $this->actingAs($user)->post('/owner/menus/'.$menu->id.'/categories/'.$category->id.'/products/'.$product->id.'/image', [
            'image' => UploadedFile::fake()->image('hawawshi.jpg', 800, 600),
        ])->assertRedirect(route('owner.menus.show', $menu));

        $path = $product->fresh()->image_path;
        $this->assertNotEmpty($path);
        Storage::disk('public')->assertExists($path);
        $this->assertStringStartsWith('restaurants/'.$restaurant->id.'/products/', $path);
    }

    public function test_owner_can_create_a_product_with_an_uploaded_image_from_the_catalog_form(): void
    {
        Storage::fake('public');
        [$user, $restaurant, $menu, $category] = $this->ownedMenu('Catalog Upload Test');
        app(TenantContext::class)->clear();

        $this->actingAs($user)->post('/owner/menus/'.$menu->id.'/categories/'.$category->id.'/products', [
            'name' => 'Catalog Image Burger',
            'description' => 'Created from the unified product catalog',
            'price' => 145.50,
            'is_available' => true,
            'is_featured' => true,
            'image' => UploadedFile::fake()->image('catalog-burger.webp', 1000, 800),
        ])->assertRedirect(route('owner.menus.show', $menu));

        $product = Product::query()
            ->where('menu_category_id', $category->id)
            ->where('name', 'Catalog Image Burger')
            ->firstOrFail();

        $this->assertTrue($product->is_available);
        $this->assertTrue($product->is_featured);
        $this->assertSame('145.50', $product->price);
        $this->assertStringStartsWith('restaurants/'.$restaurant->id.'/products/', (string) $product->image_path);
        Storage::disk('public')->assertExists((string) $product->image_path);
    }

    public function test_restaurant_owner_cannot_view_or_edit_a_different_restaurants_menu(): void
    {
        [$user] = $this->ownedMenu('Owner A');
        [, , $otherMenu, $otherCategory, $otherProduct] = $this->ownedMenu('Owner B');
        app(TenantContext::class)->clear();

        $this->actingAs($user)->get('/owner/menus/'.$otherMenu->id)->assertForbidden();

        app(TenantContext::class)->clear();
        $this->actingAs($user)->put('/owner/menus/'.$otherMenu->id.'/categories/'.$otherCategory->id.'/products/'.$otherProduct->id, [
            'name' => 'Unauthorized edit',
            'description' => null,
            'price' => 1,
            'is_available' => true,
            'is_featured' => false,
        ])->assertForbidden();

        $this->assertDatabaseMissing('products', ['id' => $otherProduct->id, 'name' => 'Unauthorized edit']);
    }

    public function test_platform_owner_can_manage_menus_across_restaurants(): void
    {
        [$platformOwner] = $this->ownedMenu('Owner A');
        [, $otherRestaurant, $otherMenu] = $this->ownedMenu('Owner B');
        $platformOwner->forceFill(['is_platform_owner' => true])->save();
        app(TenantContext::class)->clear();

        $this->actingAs($platformOwner)->get('/owner/menus')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('platformOwner', true)
                ->has('restaurants', 2));

        app(TenantContext::class)->clear();
        $this->actingAs($platformOwner)->get('/owner/menus/'.$otherMenu->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('menu.id', $otherMenu->id)
                ->where('managementBase', '/owner/menus/'.$otherMenu->id));

        app(TenantContext::class)->clear();
        $this->actingAs($platformOwner)->get('/owner/restaurants/'.$otherRestaurant->id.'/settings/orders')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('RestaurantSettings')
                ->where('settings.name', 'Owner B')
                ->where('settingsUrl', route('owner.restaurants.settings.orders.update', $otherRestaurant))
                ->where('backUrl', route('owner.menus.index')));

        app(TenantContext::class)->clear();
        $this->actingAs($platformOwner)->get('/owner/restaurants/'.$otherRestaurant->id.'/orders')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Orders/Index')
                ->where('ordersBaseUrl', '/owner/restaurants/'.$otherRestaurant->id.'/orders')
                ->where('backUrl', route('owner.menus.index'))
                ->where('ownerContext', true)
                ->where('restaurantContact.name', 'Owner B'));

        $this->assertSame('owner-b', $otherRestaurant->slug);
    }

    public function test_restaurant_owner_can_create_another_menu_from_the_owner_studio(): void
    {
        [$user, $restaurant] = $this->ownedMenu('Menu Creation');
        app(TenantContext::class)->clear();

        $template = Template::query()->where('key', 'cafe-'.$restaurant->id)->firstOrFail();

        $this->actingAs($user)->post('/owner/restaurants/'.$restaurant->id.'/menus', [
            'name' => 'منيو المشروبات',
            'template_key' => $template->key,
        ])->assertRedirect();

        $menu = Menu::query()->withoutGlobalScopes()
            ->where('restaurant_id', $restaurant->id)
            ->where('name', 'منيو المشروبات')
            ->firstOrFail();

        $this->assertSame($template->id, $menu->template_id);
        $this->assertDatabaseHas('menus', [
            'id' => $menu->id,
            'restaurant_id' => $restaurant->id,
        ]);
    }

    public function test_platform_owner_can_create_restaurants_and_reusable_generic_templates(): void
    {
        Notification::fake();
        config(['mail.default' => 'smtp']);
        [$platformOwner] = $this->ownedMenu('Platform Owner');
        $platformOwner->forceFill(['is_platform_owner' => true])->save();
        app(TenantContext::class)->clear();

        $this->actingAs($platformOwner)->post('/owner/restaurants', [
            'name' => 'New Bistro',
            'phone' => '01012345678',
            'whatsapp_phone' => '01012345678',
            'address' => 'Main Street',
            'owner_name' => 'New Bistro Owner',
            'owner_email' => 'bistro.owner@example.com',
        ])->assertRedirect(route('owner.menus.index'));

        $restaurant = Restaurant::query()->where('slug', 'new-bistro')->firstOrFail();
        $restaurantOwner = User::query()->where('email', 'bistro.owner@example.com')->firstOrFail();
        $this->assertFalse($restaurantOwner->isPlatformOwner());
        $this->assertSame('owner', $restaurantOwner->roleIn($restaurant));
        Notification::assertSentTo($restaurantOwner, ResetPassword::class);
        $this->assertDatabaseHas('branches', [
            'restaurant_id' => $restaurant->id,
            'slug' => 'main',
            'address' => 'Main Street',
        ]);

        app(TenantContext::class)->clear();
        $this->actingAs($platformOwner)->post('/owner/templates', [
            'name' => 'Warm Modern Cafe',
            'description' => 'Reusable café layout with custom colors.',
            'layout' => 'cafe',
            'primary' => '#123456',
            'accent' => '#D6A45B',
            'background' => '#FFFFFF',
            'foreground' => '#111111',
            'radius' => 'xl',
        ])->assertRedirect(route('owner.menus.index'));

        $template = Template::query()->where('name', 'Warm Modern Cafe')->firstOrFail();
        $version = $template->activeVersion()->firstOrFail();
        $this->assertTrue($template->is_active);
        $this->assertSame('cafe', $version->schema['layout']);
        $this->assertSame('#123456', $version->schema['theme']['primary']);

        app(TenantContext::class)->clear();
        $this->actingAs($platformOwner)->post('/owner/restaurants/'.$restaurant->id.'/menus', [
            'name' => 'Lunch Menu',
            'template_key' => $template->key,
        ])->assertRedirect();

        $menu = Menu::query()->withoutGlobalScopes()->where('restaurant_id', $restaurant->id)->where('name', 'Lunch Menu')->firstOrFail();
        $this->assertSame($template->id, $menu->template_id);
        $this->assertSame('#123456', strtoupper($menu->theme['primary']));
    }

    public function test_platform_owner_can_attach_an_existing_user_without_changing_the_password_or_sending_a_setup_link(): void
    {
        Notification::fake();
        [$platformOwner] = $this->ownedMenu('Platform Owner Existing Account');
        $platformOwner->forceFill(['is_platform_owner' => true])->save();
        $existingOwner = User::factory()->create(['email' => 'existing.owner@example.com', 'password' => 'Known-Existing-Pass-2026!']);
        $passwordHash = $existingOwner->password;
        app(TenantContext::class)->clear();

        $this->actingAs($platformOwner)->post('/owner/restaurants', [
            'name' => 'Existing Account Bistro',
            'owner_name' => 'Existing Account Owner',
            'owner_email' => 'EXISTING.OWNER@example.com',
        ])->assertRedirect(route('owner.menus.index'));

        $restaurant = Restaurant::query()->where('slug', 'existing-account-bistro')->firstOrFail();
        $existingOwner->refresh();
        $this->assertSame($passwordHash, $existingOwner->password);
        $this->assertSame('owner', $existingOwner->roleIn($restaurant));
        Notification::assertNothingSent();
    }

    public function test_restaurant_owner_cannot_create_platform_restaurants_or_templates(): void
    {
        [$owner] = $this->ownedMenu('Tenant Owner');
        app(TenantContext::class)->clear();

        $this->actingAs($owner)->post('/owner/restaurants', ['name' => 'Unauthorized Restaurant'])->assertForbidden();
        $this->actingAs($owner)->post('/owner/templates', [
            'name' => 'Unauthorized Template',
            'layout' => 'cafe',
            'primary' => '#111111',
            'accent' => '#222222',
            'background' => '#FFFFFF',
            'foreground' => '#000000',
            'radius' => 'xl',
        ])->assertForbidden();

        $this->assertDatabaseMissing('restaurants', ['name' => 'Unauthorized Restaurant']);
        $this->assertDatabaseMissing('templates', ['name' => 'Unauthorized Template']);
    }

    public function test_platform_owner_without_a_restaurant_membership_lands_in_owner_studio_after_login(): void
    {
        $user = User::factory()->create(['password' => 'Owner-Test-Login-2026!']);
        $user->forceFill(['is_platform_owner' => true])->save();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'Owner-Test-Login-2026!',
        ])->assertRedirect(route('owner.menus.index'))->assertSessionHasNoErrors();

        $this->get('/owner/menus')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Owner/MenuStudio')
                ->where('platformOwner', true)
                ->has('restaurants', 0));
    }

    private function ownedMenu(string $restaurantName): array
    {
        $user = User::factory()->create();
        $restaurant = Restaurant::create([
            'name' => $restaurantName,
            'slug' => str($restaurantName)->slug()->toString(),
            'is_active' => true,
        ]);
        $restaurant->users()->attach($user->id, ['role' => 'owner']);

        $template = Template::create(['key' => 'cafe-'.$restaurant->id, 'name' => 'Test Cafe', 'is_active' => true]);
        $version = $template->createVersion([
            'theme' => ['primary' => '#111827', 'accent' => '#D6A85F', 'background' => '#FFFFFF', 'foreground' => '#111827', 'radius' => 'xl'],
        ], true);

        app(TenantContext::class)->set($restaurant);
        $menu = Menu::create([
            'restaurant_id' => $restaurant->id,
            'template_id' => $template->id,
            'template_version_id' => $version->id,
            'name' => 'Main Menu',
            'slug' => 'main-menu',
            'theme' => ['primary' => '#111827', 'accent' => '#D6A85F', 'background' => '#FFFFFF', 'foreground' => '#111827', 'radius' => 'xl'],
            'is_published' => true,
        ]);
        $category = MenuCategory::create([
            'restaurant_id' => $restaurant->id,
            'menu_id' => $menu->id,
            'name' => 'الحواوشي',
            'slug' => 'hawawshi',
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $product = Product::create([
            'restaurant_id' => $restaurant->id,
            'menu_category_id' => $category->id,
            'name' => 'حواوشي سادة',
            'slug' => 'hawawshi-plain',
            'price' => 30,
            'is_available' => true,
            'is_featured' => false,
            'sort_order' => 0,
        ]);

        return [$user, $restaurant, $menu, $category, $product];
    }
}
