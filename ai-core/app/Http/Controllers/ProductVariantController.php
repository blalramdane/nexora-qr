<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductVariantController extends Controller
{
    public function store(Request $request, int $menu, int $category, int $product): RedirectResponse
    {
        $this->authorizeMenuManagement();
        $menuModel = $this->menu($menu);
        $productModel = $this->product($menuModel, $category, $product);
        $data = $this->validated($request);

        $productModel->variants()->create(array_merge(
            ['restaurant_id' => app(TenantContext::class)->id()],
            $this->variantPayload($data, $productModel)
        ));

        return to_route('menus.show', $menuModel);
    }

    public function update(Request $request, int $menu, int $category, int $product, int $variant): RedirectResponse
    {
        $this->authorizeMenuManagement();
        $menuModel = $this->menu($menu);
        $productModel = $this->product($menuModel, $category, $product);
        $variantModel = $productModel->variants()->whereKey($variant)->firstOrFail();
        $data = $this->validated($request);

        $variantModel->update($this->variantPayload($data, $productModel, $variantModel->sort_order));

        return to_route('menus.show', $menuModel);
    }

    public function destroy(int $menu, int $category, int $product, int $variant): RedirectResponse
    {
        $this->authorizeMenuManagement();
        $menuModel = $this->menu($menu);
        $productModel = $this->product($menuModel, $category, $product);
        $productModel->variants()->whereKey($variant)->firstOrFail()->delete();

        return to_route('menus.show', $menuModel);
    }

    public function reorder(Request $request, int $menu, int $category, int $product): RedirectResponse
    {
        $this->authorizeMenuManagement();
        $menuModel = $this->menu($menu);
        $productModel = $this->product($menuModel, $category, $product);
        $data = $request->validate([
            'variant_ids' => ['required', 'array', 'min:1'],
            'variant_ids.*' => ['integer', 'distinct'],
        ]);

        $variants = $productModel->variants()->get();
        $ids = array_map('intval', $data['variant_ids']);

        abort_unless(
            count($ids) === $variants->count() &&
            count(array_diff($variants->modelKeys(), $ids)) === 0,
            422,
            'Variant order must contain every variant exactly once.'
        );

        DB::transaction(function () use ($variants, $ids): void {
            foreach ($ids as $position => $id) {
                $variants->firstWhere('id', $id)->update(['sort_order' => $position]);
            }
        });

        return to_route('menus.show', $menuModel);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'pricing_mode' => ['required', 'in:fixed,delta'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'price_delta' => ['nullable', 'numeric', 'min:-99999999.99', 'max:99999999.99'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    private function variantPayload(array $data, Product $product, ?int $existingSortOrder = null): array
    {
        $sortOrder = $data['sort_order'] ?? $existingSortOrder ?? ($product->variants()->exists() ? ((int) $product->variants()->max('sort_order') + 1) : 0);

        if ($data['pricing_mode'] === 'fixed') {
            abort_unless(array_key_exists('price', $data) && $data['price'] !== null, 422, 'Fixed pricing requires a price.');

            return [
                'name' => $data['name'],
                'price' => $data['price'],
                'price_delta' => 0,
                'is_active' => $data['is_active'] ?? true,
                'sort_order' => $sortOrder,
            ];
        }

        abort_unless(array_key_exists('price_delta', $data) && $data['price_delta'] !== null, 422, 'Delta pricing requires a price delta.');

        return [
            'name' => $data['name'],
            'price' => null,
            'price_delta' => $data['price_delta'],
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $sortOrder,
        ];
    }

    private function menu(int $id): Menu
    {
        return Menu::query()->findOrFail($id);
    }

    private function product(Menu $menu, int $categoryId, int $productId): Product
    {
        $category = $menu->categories()->whereKey($categoryId)->firstOrFail();

        return $category->products()->whereKey($productId)->firstOrFail();
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
