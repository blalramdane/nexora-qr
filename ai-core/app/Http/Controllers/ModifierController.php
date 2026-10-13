<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ModifierController extends Controller
{
    public function store(Request $request, int $menu, int $category, int $product): RedirectResponse
    {
        $this->authorizeMenuManagement();
        $menuModel = $this->menu($menu);
        $productModel = $this->product($menuModel, $category, $product);
        $data = $this->validated($request);
        $this->assertGroupRules($data, $productModel);

        $productModel->modifiers()->create(array_merge(
            ['restaurant_id' => app(TenantContext::class)->id()],
            $this->modifierPayload($data, $productModel)
        ));

        return to_route(request()->routeIs('owner.*') ? 'owner.menus.show' : 'menus.show', $menuModel);
    }

    public function update(Request $request, int $menu, int $category, int $product, int $modifier): RedirectResponse
    {
        $this->authorizeMenuManagement();
        $menuModel = $this->menu($menu);
        $productModel = $this->product($menuModel, $category, $product);
        $modifierModel = $productModel->modifiers()->whereKey($modifier)->firstOrFail();
        $data = $this->validated($request);
        $this->assertGroupRules($data, $productModel, $modifierModel->id);

        $modifierModel->update($this->modifierPayload($data, $productModel, $modifierModel->sort_order));

        return to_route(request()->routeIs('owner.*') ? 'owner.menus.show' : 'menus.show', $menuModel);
    }

    public function destroy(int $menu, int $category, int $product, int $modifier): RedirectResponse
    {
        $this->authorizeMenuManagement();
        $menuModel = $this->menu($menu);
        $productModel = $this->product($menuModel, $category, $product);
        $productModel->modifiers()->whereKey($modifier)->firstOrFail()->delete();

        return to_route(request()->routeIs('owner.*') ? 'owner.menus.show' : 'menus.show', $menuModel);
    }

    public function reorder(Request $request, int $menu, int $category, int $product): RedirectResponse
    {
        $this->authorizeMenuManagement();
        $menuModel = $this->menu($menu);
        $productModel = $this->product($menuModel, $category, $product);
        $data = $request->validate([
            'modifier_ids' => ['required', 'array', 'min:1'],
            'modifier_ids.*' => ['integer', 'distinct'],
        ]);

        $modifiers = $productModel->modifiers()->get();
        $ids = array_map('intval', $data['modifier_ids']);

        abort_unless(
            count($ids) === $modifiers->count() &&
            count(array_diff($modifiers->modelKeys(), $ids)) === 0,
            422,
            'Modifier order must contain every modifier exactly once.'
        );

        DB::transaction(function () use ($modifiers, $ids): void {
            foreach ($ids as $position => $id) {
                $modifiers->firstWhere('id', $id)->update(['sort_order' => $position]);
            }
        });

        return to_route(request()->routeIs('owner.*') ? 'owner.menus.show' : 'menus.show', $menuModel);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'group_name' => ['nullable', 'string', 'max:120'],
            'group_min_choices' => ['nullable', 'integer', 'min:0', 'max:20'],
            'group_max_choices' => ['nullable', 'integer', 'min:1', 'max:20'],
            'price_delta' => ['required', 'numeric', 'min:-99999999.99', 'max:99999999.99'],
            'is_required' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    private function assertGroupRules(array $data, Product $product, ?int $excludeId = null): void
    {
        $groupName = trim((string) ($data['group_name'] ?? ''));
        if ($groupName === '') return;

        $minimum = (int) ($data['group_min_choices'] ?? 0);
        $maximum = (int) ($data['group_max_choices'] ?? 1);
        if ($minimum > $maximum) {
            throw \Illuminate\Validation\ValidationException::withMessages(['group_min_choices' => 'الحد الأدنى لازم يكون أقل من أو يساوي الحد الأقصى.']);
        }

        $existing = $product->modifiers()->where('group_name', $groupName)->when($excludeId, fn ($query) => $query->where('id', '!=', $excludeId))->get();
        if ($existing->contains(fn ($modifier) => (int) $modifier->group_min_choices !== $minimum || (int) $modifier->group_max_choices !== $maximum)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['group_name' => 'كل اختيارات المجموعة لازم يكون لها نفس الحد الأدنى والأقصى.']);
        }
    }

    private function modifierPayload(array $data, Product $product, ?int $existingSortOrder = null): array
    {
        $sortOrder = $data['sort_order'] ?? $existingSortOrder ?? ($product->modifiers()->exists() ? ((int) $product->modifiers()->max('sort_order') + 1) : 0);

        return [
            'name' => $data['name'],
            'group_name' => trim((string) ($data['group_name'] ?? '')) !== '' ? trim($data['group_name']) : null,
            'group_min_choices' => (int) ($data['group_min_choices'] ?? 0),
            'group_max_choices' => (int) ($data['group_max_choices'] ?? 1),
            'price_delta' => $data['price_delta'],
            'is_required' => trim((string) ($data['group_name'] ?? '')) === '' ? ($data['is_required'] ?? false) : false,
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
            $user?->isPlatformOwner() || $user?->isOwnerOf($restaurant) || $user?->roleIn($restaurant) === 'manager',
            403
        );
    }
}
