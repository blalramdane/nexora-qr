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
use Inertia\Inertia;
use Inertia\Response;

class MenuImportController extends Controller
{
    public function show(int $menu): Response
    {
        $this->authorizeMenuManagement();
        $menuModel = $this->menu($menu);

        return Inertia::render('Menu/Import', ['menu' => $menuModel]);
    }

    public function import(Request $request, int $menu): RedirectResponse
    {
        $this->authorizeMenuManagement();
        $menuModel = $this->menu($menu);
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $handle = fopen($data['file']->getRealPath(), 'r');
        abort_unless($handle !== false, 422, 'تعذر قراءة الملف.');

        try {
            $headers = fgetcsv($handle);
            abort_unless(is_array($headers) && count($headers) >= 3, 422, 'الملف لازم يحتوي على صف عناوين وأعمدة البيانات الأساسية.');
            $headers = array_map(fn ($header) => Str::of((string) $header)->replace("\xEF\xBB\xBF", '')->trim()->lower()->replace(' ', '_')->toString(), $headers);
            $required = ['category', 'product', 'price'];
            abort_unless(count(array_intersect($required, $headers)) === count($required), 422, 'الأعمدة المطلوبة: category, product, price.');

            $rows = [];
            $line = 1;
            while (($values = fgetcsv($handle)) !== false) {
                $line++;
                if (count(array_filter($values, fn ($value) => trim((string) $value) !== '')) === 0) continue;
                if (count($values) !== count($headers)) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['file' => "عدد الأعمدة غير متطابق في السطر {$line}."]);
                }
                $row = array_combine($headers, array_map(fn ($value) => trim((string) $value), $values));
                if (($row['category'] ?? '') === '' || ($row['product'] ?? '') === '' || !isset($row['price']) || !is_numeric($row['price']) || (float) $row['price'] < 0) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['file' => "راجع القسم واسم المنتج والسعر في السطر {$line}."]);
                }
                $rows[] = $row;
                if (count($rows) > 2000) throw \Illuminate\Validation\ValidationException::withMessages(['file' => 'الحد الأقصى 2000 سطر في الملف الواحد.']);
            }
            abort_unless(count($rows) > 0, 422, 'الملف لا يحتوي على أصناف للاستيراد.');

            $summary = DB::transaction(function () use ($rows, $menuModel): array {
                $restaurantId = app(TenantContext::class)->id();
                $categoriesCreated = $productsCreated = $productsUpdated = $variantsCreated = $modifiersCreated = 0;

                foreach ($rows as $row) {
                    $category = $menuModel->categories()->whereRaw('LOWER(name) = ?', [mb_strtolower($row['category'])])->first();
                    if (!$category) {
                        $category = $menuModel->categories()->create([
                            'restaurant_id' => $restaurantId,
                            'name' => $row['category'],
                            'slug' => $this->uniqueSlug($menuModel, $row['category']),
                            'sort_order' => (int) $menuModel->categories()->max('sort_order') + 1,
                        ]);
                        $categoriesCreated++;
                    }

                    $product = $category->products()->whereRaw('LOWER(name) = ?', [mb_strtolower($row['product'])])->first();
                    if (!$product) {
                        $product = $category->products()->create([
                            'restaurant_id' => $restaurantId,
                            'name' => $row['product'],
                            'slug' => $this->uniqueProductSlug($category, $row['product']),
                            'description' => $row['description'] ?? null,
                            'price' => (float) $row['price'],
                            'sort_order' => (int) $category->products()->max('sort_order') + 1,
                        ]);
                        $productsCreated++;
                    } else {
                        $product->update(['price' => (float) $row['price'], 'description' => ($row['description'] ?? '') !== '' ? $row['description'] : $product->description]);
                        $productsUpdated++;
                    }

                    if (($row['variant'] ?? '') !== '') {
                        $variant = $product->variants()->whereRaw('LOWER(name) = ?', [mb_strtolower($row['variant'])])->first();
                        $variantData = ['restaurant_id' => $restaurantId, 'price' => is_numeric($row['variant_price'] ?? null) && $row['variant_price'] !== '' ? (float) $row['variant_price'] : null, 'price_delta' => is_numeric($row['variant_delta'] ?? null) ? (float) $row['variant_delta'] : 0];
                        if ($variant) $variant->update($variantData);
                        else { $product->variants()->create($variantData + ['name' => $row['variant'], 'sort_order' => (int) $product->variants()->max('sort_order') + 1]); $variantsCreated++; }
                    }

                    if (($row['modifier'] ?? '') !== '') {
                        $modifier = $product->modifiers()->whereRaw('LOWER(name) = ?', [mb_strtolower($row['modifier'])])->first();
                        $groupName = trim((string) ($row['modifier_group'] ?? '')) ?: null;
                        $modifierData = ['restaurant_id' => $restaurantId, 'price_delta' => is_numeric($row['modifier_price'] ?? null) ? (float) $row['modifier_price'] : 0, 'is_required' => $groupName === null && filter_var($row['modifier_required'] ?? false, FILTER_VALIDATE_BOOLEAN), 'group_name' => $groupName, 'group_min_choices' => max(0, min(20, (int) ($row['group_min'] ?? 0))), 'group_max_choices' => max(1, min(20, (int) ($row['group_max'] ?? 1)))];
                        if ($modifier) $modifier->update($modifierData);
                        else { $product->modifiers()->create($modifierData + ['name' => $row['modifier'], 'sort_order' => (int) $product->modifiers()->max('sort_order') + 1]); $modifiersCreated++; }
                    }
                }

                return compact('categoriesCreated', 'productsCreated', 'productsUpdated', 'variantsCreated', 'modifiersCreated');
            });
        } finally {
            fclose($handle);
        }

        $message = "تم الاستيراد. أقسام جديدة: {$summary['categoriesCreated']}، منتجات جديدة: {$summary['productsCreated']}، منتجات تم تحديثها: {$summary['productsUpdated']}، أحجام جديدة: {$summary['variantsCreated']}، إضافات جديدة: {$summary['modifiersCreated']}.";
        return to_route('menus.import.show', $menuModel)->with('success', $message);
    }

    private function menu(int $id): Menu
    {
        return Menu::query()->where('restaurant_id', app(TenantContext::class)->id())->findOrFail($id);
    }

    private function uniqueSlug(Menu $menu, string $name): string
    {
        $base = Str::slug($name) ?: 'category'; $slug = $base; $suffix = 2;
        while ($menu->categories()->where('slug', $slug)->exists()) $slug = $base.'-'. $suffix++;
        return $slug;
    }

    private function uniqueProductSlug(MenuCategory $category, string $name): string
    {
        $base = Str::slug($name) ?: 'product'; $slug = $base; $suffix = 2;
        while ($category->products()->where('slug', $slug)->exists()) $slug = $base.'-'. $suffix++;
        return $slug;
    }

    private function authorizeMenuManagement(): void
    {
        $restaurant = app(TenantContext::class)->restaurant();
        abort_unless(request()->user()?->isOwnerOf($restaurant) || request()->user()?->roleIn($restaurant) === 'manager', 403);
    }
}
