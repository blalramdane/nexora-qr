<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuScanJob;
use App\Models\Restaurant;
use App\Models\Template;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MenuScanWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_scan_page_is_inertia_and_shows_only_the_active_restaurants_menus(): void
    {
        [$owner, $restaurant, $menu] = $this->ownedMenu('Scan Owner', 'scan-owner');
        [, $otherRestaurant, $otherMenu] = $this->ownedMenu('Scan Other', 'scan-other');
        app(TenantContext::class)->clear();

        $this->actingAs($owner)->get('/owner/restaurants/'.$restaurant->id.'/menu-scans')
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Menu/ScanMenu')
            ->where('restaurantId', $restaurant->id)
            ->has('menus', 1)
            ->where('menus.0.id', $menu->id)
            ->missing('menus.1'));
    }

    public function test_menu_scan_upload_rejects_a_menu_from_another_restaurant(): void
    {
        Storage::fake('public');
        [$owner, $restaurant] = $this->ownedMenu('Scan Upload Owner', 'scan-upload-owner');
        [, $otherRestaurant, $otherMenu] = $this->ownedMenu('Scan Upload Other', 'scan-upload-other');
        app(TenantContext::class)->clear();

        $this->actingAs($owner)->post('/owner/restaurants/'.$restaurant->id.'/menu-scans', [
            'menu_id' => $otherMenu->id,
            'images' => [UploadedFile::fake()->image('paper-menu.jpg')],
        ])->assertNotFound();

        $this->assertDatabaseCount('menu_scan_jobs', 0);
    }

    public function test_review_requires_confirmed_prices_and_imports_only_into_the_selected_menu(): void
    {
        [$owner, $restaurant, $menu] = $this->ownedMenu('Scan Review Owner', 'scan-review-owner');
        [, $otherRestaurant, $otherMenu] = $this->ownedMenu('Scan Review Other', 'scan-review-other');
        $job = MenuScanJob::withoutGlobalScopes()->create([
            'restaurant_id' => $restaurant->id,
            'menu_id' => $menu->id,
            'user_id' => $owner->id,
            'status' => 'extracted',
            'image_paths' => [],
            'extracted_data' => ['categories' => [['name' => 'Burger', 'items' => [['name' => 'Classic Burger', 'price' => null]]]]],
        ]);
        $otherJob = MenuScanJob::withoutGlobalScopes()->create([
            'restaurant_id' => $otherRestaurant->id,
            'menu_id' => $otherMenu->id,
            'user_id' => $owner->id,
            'status' => 'extracted',
            'image_paths' => [],
            'extracted_data' => ['categories' => []],
        ]);
        app(TenantContext::class)->clear();

        $baseUrl = '/owner/restaurants/'.$restaurant->id.'/menu-scans';
        $this->actingAs($owner)->postJson($baseUrl.'/'.$job->id.'/review', [
            'reviewed_data' => ['categories' => [['name' => 'Burger', 'items' => [['name' => 'Classic Burger', 'price' => null]]]]],
        ])->assertUnprocessable();
        $this->assertSame('extracted', $job->fresh()->status);
        $this->assertDatabaseCount('products', 0);

        $this->actingAs($owner)->postJson($baseUrl.'/'.$job->id.'/review', [
            'reviewed_data' => ['categories' => [['name' => 'Burger', 'items' => [['name' => 'Classic Burger', 'price' => 85]]]]],
        ])->assertOk()->assertJsonPath('status', 'reviewed');

        $this->actingAs($owner)->postJson($baseUrl.'/'.$job->id.'/approve')
            ->assertOk()->assertJsonPath('summary.products', 1)->assertJsonPath('summary.categories', 1);

        $this->assertDatabaseHas('products', [
            'restaurant_id' => $restaurant->id,
            'menu_category_id' => MenuCategory::where('menu_id', $menu->id)->value('id'),
            'name' => 'Classic Burger',
            'price' => 85,
        ]);
        $this->assertDatabaseMissing('products', ['restaurant_id' => $otherRestaurant->id, 'name' => 'Classic Burger']);

        app(TenantContext::class)->clear();
        $this->actingAs($owner)->postJson($baseUrl.'/'.$otherJob->id.'/approve')->assertNotFound();
    }

    /** @return array{0:User,1:Restaurant,2:Menu} */
    private function ownedMenu(string $name, string $slug): array
    {
        $restaurant = Restaurant::create(['name' => $name, 'slug' => $slug, 'is_active' => true]);
        $owner = User::create(['name' => 'Owner '.$slug, 'email' => $slug.'@example.test', 'password' => 'test-password']);
        $owner->restaurants()->attach($restaurant->id, ['role' => 'owner']);
        app(TenantContext::class)->set($restaurant);

        $template = Template::create(['key' => 'fast-food-'.$slug, 'name' => 'Fast Food '.$slug, 'is_active' => true]);
        $version = $template->createVersion(['layout' => 'fast-food'], true);
        $menu = Menu::create([
            'restaurant_id' => $restaurant->id,
            'template_id' => $template->id,
            'template_version_id' => $version->id,
            'name' => 'Menu '.$slug,
            'slug' => 'menu-'.$slug,
        ]);

        return [$owner, $restaurant, $menu];
    }
}
