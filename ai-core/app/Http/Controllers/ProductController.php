<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function store(Request $request, int $menu, int $category): RedirectResponse
    {
        $this->authorizeMenuManagement();

        $menuModel = $this->menu($menu);
        $categoryModel = $this->category($menuModel, $category);

        $data = $this->validated($request);
        $categoryModel->products()->create([
            'restaurant_id' => app(TenantContext::class)->id(),
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($categoryModel, $data['name']),
            'description' => $data['description'] ?? null,
            'price' => $data['price'],
            'image_path' => $data['image_path'] ?? null,
            'is_available' => $data['is_available'] ?? true,
            'is_featured' => $data['is_featured'] ?? false,
            'sort_order' => $data['sort_order'] ?? ((int) $categoryModel->products()->max('sort_order') + 1),
        ]);

        return to_route('menus.show', $menuModel);
    }

    public function update(Request $request, int $menu, int $category, int $product): RedirectResponse
    {
        $this->authorizeMenuManagement();

        $menuModel = $this->menu($menu);
        $categoryModel = $this->category($menuModel, $category);
        $productModel = $categoryModel->products()->whereKey($product)->firstOrFail();

        $data = $this->validated($request);
        $productModel->update([
            'name' => $data['name'],
            'slug' => $productModel->name !== $data['name']
                ? $this->uniqueSlug($categoryModel, $data['name'], $productModel->id)
                : $productModel->slug,
            'description' => $data['description'] ?? null,
            'price' => $data['price'],
            'image_path' => $data['image_path'] ?? null,
            'is_available' => $data['is_available'] ?? $productModel->is_available,
            'is_featured' => $data['is_featured'] ?? $productModel->is_featured,
            'sort_order' => $data['sort_order'] ?? $productModel->sort_order,
        ]);

        return to_route('menus.show', $menuModel);
    }

    public function destroy(int $menu, int $category, int $product): RedirectResponse
    {
        $this->authorizeMenuManagement();

        $menuModel = $this->menu($menu);
        $categoryModel = $this->category($menuModel, $category);
        $categoryModel->products()->whereKey($product)->firstOrFail()->delete();

        return to_route('menus.show', $menuModel);
    }

    public function reorder(Request $request, int $menu, int $category): RedirectResponse
    {
        $this->authorizeMenuManagement();

        $menuModel = $this->menu($menu);
        $categoryModel = $this->category($menuModel, $category);
        $data = $request->validate([
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer', 'distinct'],
        ]);

        $products = $categoryModel->products()->get();
        $ids = array_map('intval', $data['product_ids']);

        abort_unless(
            count($ids) === $products->count() &&
            count(array_diff($products->modelKeys(), $ids)) === 0,
            422,
            'Product order must contain every product exactly once.'
        );

        DB::transaction(function () use ($products, $ids): void {
            foreach ($ids as $position => $id) {
                $products->firstWhere('id', $id)->update(['sort_order' => $position]);
            }
        });

        return to_route('menus.show', $menuModel);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'image_path' => ['nullable', 'string', 'max:2048'],
            'is_available' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    private function menu(int $id): Menu
    {
        return Menu::query()->findOrFail($id);
    }

    private function category(Menu $menu, int $id): MenuCategory
    {
        return $menu->categories()->whereKey($id)->firstOrFail();
    }

    private function uniqueSlug(MenuCategory $category, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'product';
        $slug = $base;
        $suffix = 2;

        while (true) {
            $query = Product::query()
                ->where('menu_category_id', $category->id)
                ->where('slug', $slug);

            if ($ignoreId !== null) {
                $query->where('id', '<>', $ignoreId);
            }

            if (!$query->exists()) {
                return $slug;
            }

            $slug = $base . '-' . $suffix++;
        }
    }

    private function authorizeMenuManagement(): void
    {
        $user = request()->user();
        $restaurant = app(TenantContext::class)->restaurant();

        abort_unless(
            $user?->isOwnerOf($restaurant) || $user?->roleIn($restaurant) === 'manager',
            403
        );
    }
}
