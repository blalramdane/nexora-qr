<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use App\Models\MenuCategory;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MenuCategoryController extends Controller
{
    public function store(Request $request, int $menu): RedirectResponse
    {
        $this->authorizeMenuManagement();

        $menuModel = $this->menu($menu);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $menuModel->categories()->create([
            'restaurant_id' => app(TenantContext::class)->id(),
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($menuModel, $data['name']),
            'description' => $data['description'] ?? null,
            'sort_order' => $data['sort_order'] ?? ((int) $menuModel->categories()->max('sort_order') + 1),
            'is_active' => $data['is_active'] ?? true,
        ]);

        return to_route('menus.show', $menuModel);
    }

    public function update(Request $request, int $menu, int $category): RedirectResponse
    {
        $this->authorizeMenuManagement();

        $menuModel = $this->menu($menu);
        $categoryModel = $this->category($menuModel, $category);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $categoryModel->update([
            'name' => $data['name'],
            'slug' => $categoryModel->name !== $data['name']
                ? $this->uniqueSlug($menuModel, $data['name'], $categoryModel->id)
                : $categoryModel->slug,
            'description' => $data['description'] ?? null,
            'sort_order' => $data['sort_order'] ?? $categoryModel->sort_order,
            'is_active' => $data['is_active'] ?? $categoryModel->is_active,
        ]);

        return to_route('menus.show', $menuModel);
    }

    public function destroy(int $menu, int $category): RedirectResponse
    {
        $this->authorizeMenuManagement();

        $menuModel = $this->menu($menu);
        $this->category($menuModel, $category)->delete();

        return to_route('menus.show', $menuModel);
    }

    public function reorder(Request $request, int $menu): RedirectResponse
    {
        $this->authorizeMenuManagement();

        $menuModel = $this->menu($menu);
        $data = $request->validate([
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => ['integer', 'distinct'],
        ]);

        $categories = $menuModel->categories()->get();
        $ids = array_map('intval', $data['category_ids']);

        abort_unless(
            count($ids) === $categories->count() &&
            count(array_diff($categories->modelKeys(), $ids)) === 0,
            422,
            'Category order must contain every category exactly once.'
        );

        DB::transaction(function () use ($categories, $ids): void {
            foreach ($ids as $position => $id) {
                $categories->firstWhere('id', $id)->update(['sort_order' => $position]);
            }
        });

        return to_route('menus.show', $menuModel);
    }

    private function menu(int $id): Menu
    {
        return Menu::query()->findOrFail($id);
    }

    private function category(Menu $menu, int $id): MenuCategory
    {
        return $menu->categories()->whereKey($id)->firstOrFail();
    }

    private function uniqueSlug(Menu $menu, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;

        $query = MenuCategory::query()
            ->where('menu_id', $menu->id)
            ->where('slug', $slug);

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        $suffix = 2;
        while ($query->exists()) {
            $slug = $base . '-' . $suffix++;
            $query = MenuCategory::query()
                ->where('menu_id', $menu->id)
                ->where('slug', $slug);

            if ($ignoreId !== null) {
                $query->whereKeyNot($ignoreId);
            }
        }

        return $slug;
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
