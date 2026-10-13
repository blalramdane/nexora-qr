<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use App\Services\MenuPhotoScanner;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;
use Inertia\Inertia;
use Inertia\Response;

class MenuImportController extends Controller
{
    public function show(int $menu): Response
    {
        $this->authorizeMenuManagement();
        $menuModel = $this->menu($menu);

        return Inertia::render('Menu/Import', [
            'menu' => $menuModel,
            'managementBase' => request()->routeIs('owner.*') ? '/owner/menus/'.$menuModel->id : '/menus/'.$menuModel->id,
            'menusIndexUrl' => request()->routeIs('owner.*') ? route('owner.menus.index') : route('menus.index'),
            'photoScanEnabled' => trim((string) config('services.openai.api_key')) !== '',
        ]);
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
        return to_route(request()->routeIs('owner.*') ? 'owner.menus.import.show' : 'menus.import.show', $menuModel)->with('success', $message);
    }

    /** Read photos into a draft only. The user must review it before importing. */
    public function scanPhotos(Request $request, int $menu): RedirectResponse
    {
        $this->authorizeMenuManagement();
        $menuModel = $this->menu($menu);
        $data = $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:8'],
            'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);

        try {
            $draft = app(MenuPhotoScanner::class)->scan($data['images']);
        } catch (ConnectionException) {
            return back()->withErrors(['images' => 'تعذر الاتصال بخدمة قراءة المنيو. راجع اتصال السيرفر وحاول مرة أخرى.']);
        } catch (RequestException) {
            return back()->withErrors(['images' => 'خدمة قراءة الصور لم تقبل الطلب. راجع إعدادات API وحدود الاستخدام على السيرفر.']);
        } catch (RuntimeException|JsonException $exception) {
            return back()->withErrors(['images' => $exception->getMessage()]);
        }

        $request->session()->put($this->scanSessionKey($menuModel), $draft);

        return to_route(request()->routeIs('owner.*') ? 'owner.menus.scan.review' : 'menus.scan.review', $menuModel)
            ->with('success', 'تم استخراج مسودة من الصور. راجع أسماء الأصناف وكل الأسعار قبل اعتمادها.');
    }

    /** Display the scan draft associated with this user, restaurant and menu. */
    public function reviewScan(Request $request, int $menu): Response|RedirectResponse
    {
        $this->authorizeMenuManagement();
        $menuModel = $this->menu($menu);
        $draft = $request->session()->get($this->scanSessionKey($menuModel));

        if (! is_array($draft) || ! is_array($draft['categories'] ?? null)) {
            return to_route(request()->routeIs('owner.*') ? 'owner.menus.import.show' : 'menus.import.show', $menuModel)
                ->withErrors(['images' => 'مفيش مسودة صور للمراجعة. ارفع صور المنيو الأول.']);
        }

        return Inertia::render('Menu/ScanReview', [
            'menu' => ['id' => $menuModel->id, 'name' => $menuModel->name],
            'draft' => $draft,
            'managementBase' => request()->routeIs('owner.*') ? '/owner/menus/'.$menuModel->id : '/menus/'.$menuModel->id,
            'menusIndexUrl' => request()->routeIs('owner.*') ? route('owner.menus.index') : route('menus.index'),
        ]);
    }

    /** Import only the reviewed and validated draft after explicit confirmation. */
    public function applyScan(Request $request, int $menu): RedirectResponse
    {
        $this->authorizeMenuManagement();
        $menuModel = $this->menu($menu);
        $sessionKey = $this->scanSessionKey($menuModel);
        abort_unless(is_array($request->session()->get($sessionKey)), 419, 'انتهت جلسة مراجعة الصور. ارفع الصور مرة أخرى.');

        $data = $request->validate([
            'categories' => ['required', 'array', 'min:1', 'max:100'],
            'categories.*.name' => ['required', 'string', 'max:120'],
            'categories.*.products' => ['required', 'array', 'max:500'],
            'categories.*.products.*.name' => ['required', 'string', 'max:180'],
            'categories.*.products.*.description' => ['nullable', 'string', 'max:1000'],
            'categories.*.products.*.price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'categories.*.products.*.confidence' => ['nullable', 'in:high,medium,low'],
            'categories.*.products.*.variants' => ['sometimes', 'array', 'max:30'],
            'categories.*.products.*.variants.*.name' => ['required', 'string', 'max:100'],
            'categories.*.products.*.variants.*.price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
        ]);

        $productCount = collect($data['categories'])->sum(fn (array $category) => count($category['products']));
        abort_if($productCount < 1 || $productCount > 500, 422, 'لازم تحتوي المسودة على صنف واحد على الأقل وبحد أقصى 500 صنف.');

        $summary = DB::transaction(function () use ($data, $menuModel): array {
            $restaurantId = app(TenantContext::class)->id();
            $categoriesCreated = $productsCreated = $productsUpdated = $variantsCreated = $variantsUpdated = 0;

            foreach ($data['categories'] as $categoryData) {
                $categoryName = trim($categoryData['name']);
                $category = $menuModel->categories()->whereRaw('LOWER(name) = ?', [mb_strtolower($categoryName)])->first();
                if (! $category) {
                    $category = $menuModel->categories()->create([
                        'restaurant_id' => $restaurantId,
                        'name' => $categoryName,
                        'slug' => $this->uniqueSlug($menuModel, $categoryName),
                        'sort_order' => (int) $menuModel->categories()->max('sort_order') + 1,
                    ]);
                    $categoriesCreated++;
                }

                foreach ($categoryData['products'] as $productData) {
                    $productName = trim($productData['name']);
                    $product = $category->products()->whereRaw('LOWER(name) = ?', [mb_strtolower($productName)])->first();
                    $description = trim((string) ($productData['description'] ?? ''));
                    if (! $product) {
                        $product = $category->products()->create([
                            'restaurant_id' => $restaurantId,
                            'name' => $productName,
                            'slug' => $this->uniqueProductSlug($category, $productName),
                            'description' => $description !== '' ? $description : null,
                            'price' => (float) $productData['price'],
                            'sort_order' => (int) $category->products()->max('sort_order') + 1,
                        ]);
                        $productsCreated++;
                    } else {
                        $updates = ['name' => $productName, 'price' => (float) $productData['price']];
                        if ($description !== '') $updates['description'] = $description;
                        $product->update($updates);
                        $productsUpdated++;
                    }

                    foreach (($productData['variants'] ?? []) as $variantData) {
                        $variantName = trim($variantData['name']);
                        $variant = $product->variants()->whereRaw('LOWER(name) = ?', [mb_strtolower($variantName)])->first();
                        $values = ['restaurant_id' => $restaurantId, 'price' => (float) $variantData['price'], 'price_delta' => 0];
                        if ($variant) {
                            $variant->update($values + ['name' => $variantName]);
                            $variantsUpdated++;
                        } else {
                            $product->variants()->create($values + ['name' => $variantName, 'sort_order' => (int) $product->variants()->max('sort_order') + 1]);
                            $variantsCreated++;
                        }
                    }
                }
            }

            return compact('categoriesCreated', 'productsCreated', 'productsUpdated', 'variantsCreated', 'variantsUpdated');
        });

        $request->session()->forget($sessionKey);
        $message = "تم اعتماد مسودة الصور. أقسام جديدة: {$summary['categoriesCreated']}، منتجات جديدة: {$summary['productsCreated']}، منتجات محدثة: {$summary['productsUpdated']}، أحجام جديدة: {$summary['variantsCreated']}، أحجام محدثة: {$summary['variantsUpdated']}.";

        return to_route(request()->routeIs('owner.*') ? 'owner.menus.show' : 'menus.show', $menuModel)->with('success', $message);
    }

    private function menu(int $id): Menu
    {
        return Menu::query()->where('restaurant_id', app(TenantContext::class)->id())->findOrFail($id);
    }

    private function scanSessionKey(Menu $menu): string
    {
        $userId = (string) request()->user()->id;
        return 'nexora_menu_photo_scan_'.$userId.'_'.$menu->restaurant_id.'_'.$menu->id;
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
        abort_unless(request()->user()?->isPlatformOwner() || request()->user()?->isOwnerOf($restaurant) || request()->user()?->roleIn($restaurant) === 'manager', 403);
    }
}
