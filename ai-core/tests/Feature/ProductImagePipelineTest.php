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
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImagePipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_upload_replace_and_delete_product_image(): void
    {
        Storage::fake('public');
        [$restaurant, $user, $menu, $category, $product] = $this->context();

        $this->actingAs($user)
            ->post($this->url($menu, $category, $product), [
                'image' => UploadedFile::fake()->image('burger.jpg', 1200, 800),
            ])
            ->assertRedirect("/menus/{$menu->id}");

        $path = $product->fresh()->image_path;
        $this->assertNotNull($path);
        $this->assertStringStartsWith('restaurants/'.$restaurant->id.'/products/', $path);
        Storage::disk('public')->assertExists($path);

        $this->actingAs($user)
            ->post($this->url($menu, $category, $product), [
                'image' => UploadedFile::fake()->image('burger-new.png', 900, 900),
            ])
            ->assertRedirect("/menus/{$menu->id}");

        $newPath = $product->fresh()->image_path;
        $this->assertNotSame($path, $newPath);
        Storage::disk('public')->assertMissing($path);
        Storage::disk('public')->assertExists($newPath);

        $this->actingAs($user)
            ->delete($this->url($menu, $category, $product))
            ->assertRedirect("/menus/{$menu->id}");

        $this->assertNull($product->fresh()->image_path);
        Storage::disk('public')->assertMissing($newPath);
    }

    public function test_upload_rejects_non_images_and_oversized_files(): void
    {
        Storage::fake('public');
        [, $user, $menu, $category, $product] = $this->context();

        $this->actingAs($user)
            ->post($this->url($menu, $category, $product), [
                'image' => UploadedFile::fake()->create('payload.php', 100, 'text/x-php'),
            ])
            ->assertSessionHasErrors('image');

        $this->actingAs($user)
            ->post($this->url($menu, $category, $product), [
                'image' => UploadedFile::fake()->create('huge.jpg', 6000, 'image/jpeg'),
            ])
            ->assertSessionHasErrors('image');

        $this->assertNull($product->fresh()->image_path);
    }

    public function test_upload_cannot_cross_tenant_boundary(): void
    {
        Storage::fake('public');
        [$restaurantA, $userA, $menuA, $categoryA, $productA] = $this->context('A', 'a');

        $restaurantB = Restaurant::create(['name' => 'B', 'slug' => 'b']);
        app(TenantContext::class)->set($restaurantB);
        $template = Template::firstOrFail();
        $menuB = Menu::create(['restaurant_id' => $restaurantB->id, 'template_id' => $template->id, 'name' => 'B Menu', 'slug' => 'b-menu']);
        $categoryB = MenuCategory::create(['restaurant_id' => $restaurantB->id, 'menu_id' => $menuB->id, 'name' => 'Burgers', 'slug' => 'burgers']);
        $productB = Product::create(['restaurant_id' => $restaurantB->id, 'menu_category_id' => $categoryB->id, 'name' => 'B Product', 'slug' => 'b-product', 'price' => 90]);

        app(TenantContext::class)->set($restaurantA);

        $this->actingAs($userA)
            ->post($this->url($menuA, $categoryA, $productB), [
                'image' => UploadedFile::fake()->image('hijack.jpg'),
            ])
            ->assertNotFound();

        $this->assertNull($productB->fresh()->image_path);
        $this->assertSame($restaurantA->id, $productA->fresh()->restaurant_id);
    }

    private function url(Menu $menu, MenuCategory $category, Product $product): string
    {
        return "/menus/{$menu->id}/categories/{$category->id}/products/{$product->id}/image";
    }

    private function context(string $name = 'A', string $slug = 'a'): array
    {
        $restaurant = Restaurant::create(['name' => $name, 'slug' => $slug]);
        $user = User::factory()->create();
        $restaurant->users()->attach($user->id, ['role' => 'owner']);
        $template = Template::create(['key' => 'fast-food', 'name' => 'Fast Food']);
        app(TenantContext::class)->set($restaurant);
        $menu = Menu::create(['restaurant_id' => $restaurant->id, 'template_id' => $template->id, 'name' => 'Menu', 'slug' => 'menu']);
        $category = MenuCategory::create(['restaurant_id' => $restaurant->id, 'menu_id' => $menu->id, 'name' => 'Burgers', 'slug' => 'burgers']);
        $product = Product::create(['restaurant_id' => $restaurant->id, 'menu_category_id' => $category->id, 'name' => 'Classic', 'slug' => 'classic', 'price' => 120]);

        return [$restaurant, $user, $menu, $category, $product];
    }
}
