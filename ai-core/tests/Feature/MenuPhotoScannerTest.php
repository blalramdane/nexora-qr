<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\Restaurant;
use App\Models\Template;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MenuPhotoScannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_photo_scan_creates_a_review_draft_without_writing_menu_products(): void
    {
        [$user, $menu] = $this->menuForOwner();
        config()->set('services.openai.api_key', 'test-key');
        Http::fake(['api.openai.com/v1/responses' => Http::response($this->scanResponse(), 200)]);

        $this->actingAs($user)
            ->post(route('menus.scan', $menu), ['images' => [UploadedFile::fake()->image('menu.jpg')]])
            ->assertRedirect(route('menus.scan.review', $menu));

        $this->assertDatabaseCount('products', 0);
        $this->actingAs($user)->get(route('menus.scan.review', $menu))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Menu/ScanReview')
                ->where('draft.categories.0.name', 'برجر')
                ->where('draft.categories.0.products.0.name', 'كلاسيك برجر')
                ->where('draft.categories.0.products.0.price', 125));

        Http::assertSent(fn (ClientRequest $request) => $request->url() === 'https://api.openai.com/v1/responses'
            && $request->hasHeader('Authorization', 'Bearer test-key')
            && $request['store'] === false);
    }

    public function test_reviewed_photo_draft_is_imported_only_after_explicit_confirmation(): void
    {
        [$user, $menu] = $this->menuForOwner();
        config()->set('services.openai.api_key', 'test-key');
        Http::fake(['api.openai.com/v1/responses' => Http::response($this->scanResponse(), 200)]);

        $this->actingAs($user)->post(route('menus.scan', $menu), ['images' => [UploadedFile::fake()->image('menu.jpg')]])
            ->assertRedirect(route('menus.scan.review', $menu));
        $this->assertDatabaseCount('products', 0);

        $this->actingAs($user)->post(route('menus.scan.apply', $menu), ['categories' => [[
            'name' => 'برجر',
            'products' => [[
                'name' => 'كلاسيك برجر',
                'description' => 'لحم وجبنة',
                'price' => '130',
                'confidence' => 'high',
                'variants' => [['name' => 'دبل', 'price' => '175']],
            ]],
        ]]])->assertRedirect(route('menus.show', $menu));

        $this->assertDatabaseHas('menu_categories', ['menu_id' => $menu->id, 'name' => 'برجر']);
        $this->assertDatabaseHas('products', ['name' => 'كلاسيك برجر', 'price' => 130]);
        $this->assertDatabaseHas('product_variants', ['name' => 'دبل', 'price' => 175]);
        $this->assertDatabaseCount('products', 1);
    }

    public function test_photo_draft_rejects_unreviewed_missing_prices_without_writing_products(): void
    {
        [$user, $menu] = $this->menuForOwner();
        config()->set('services.openai.api_key', 'test-key');
        Http::fake(['api.openai.com/v1/responses' => Http::response($this->scanResponse(), 200)]);

        $this->actingAs($user)->post(route('menus.scan', $menu), ['images' => [UploadedFile::fake()->image('menu.jpg')]])
            ->assertRedirect(route('menus.scan.review', $menu));

        $this->actingAs($user)->post(route('menus.scan.apply', $menu), ['categories' => [[
            'name' => 'برجر',
            'products' => [[
                'name' => 'كلاسيك برجر', 'description' => null, 'price' => '', 'confidence' => 'low', 'variants' => [],
            ]],
        ]]])->assertSessionHasErrors('categories.0.products.0.price');

        $this->assertDatabaseCount('products', 0);
    }

    private function menuForOwner(): array
    {
        $restaurant = Restaurant::create(['name' => 'مطعم تجريبي', 'slug' => 'photo-scan-test']);
        $user = User::factory()->create();
        $restaurant->users()->attach($user->id, ['role' => 'owner']);
        $template = Template::create(['key' => 'fast-food', 'name' => 'Fast Food', 'is_active' => true]);
        $version = $template->versions()->create(['version' => 1, 'schema' => ['layout' => 'fast-food'], 'is_active' => true]);
        app(TenantContext::class)->set($restaurant);
        $menu = Menu::create([
            'restaurant_id' => $restaurant->id,
            'template_id' => $template->id,
            'template_version_id' => $version->id,
            'name' => 'منيو اختبار الصور',
            'slug' => 'photo-scan-menu',
        ]);

        return [$user, $menu];
    }

    private function scanResponse(): array
    {
        return [
            'output_text' => json_encode(['categories' => [[
                'name' => 'برجر',
                'products' => [[
                    'name' => 'كلاسيك برجر',
                    'description' => 'لحم وجبنة',
                    'price' => 125,
                    'confidence' => 'high',
                    'variants' => [['name' => 'دبل', 'price' => 175]],
                ]],
            ]]], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ];
    }
}
