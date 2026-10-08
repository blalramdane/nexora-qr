<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;

class MenuPublishController extends Controller
{
    public function publish(int $menu): RedirectResponse
    {
        $this->authorizeMenuManagement();

        $menuModel = Menu::query()
            ->with(['templateVersion', 'categories.products'])
            ->findOrFail($menu);

        abort_unless($menuModel->template_id && $menuModel->template_version_id, 422, 'المنيو لازم يكون مربوط بـ Template Version.');
        abort_unless($menuModel->templateVersion?->is_active, 422, 'نسخة الـTemplate الحالية غير مفعّلة.');

        $hasActiveCategory = $menuModel->categories->contains(fn ($category) => $category->is_active);
        abort_unless($hasActiveCategory, 422, 'لازم يكون فيه قسم واحد على الأقل مفعّل.');

        $hasAvailableProduct = $menuModel->categories
            ->where('is_active', true)
            ->flatMap(fn ($category) => $category->products)
            ->contains(fn ($product) => $product->is_available);

        abort_unless($hasAvailableProduct, 422, 'لازم يكون فيه منتج واحد متاح على الأقل.');

        $menuModel->update(['is_published' => true]);

        return to_route('menus.show', $menuModel);
    }

    public function unpublish(int $menu): RedirectResponse
    {
        $this->authorizeMenuManagement();

        $menuModel = Menu::query()->findOrFail($menu);
        $menuModel->update(['is_published' => false]);

        return to_route('menus.show', $menuModel);
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
