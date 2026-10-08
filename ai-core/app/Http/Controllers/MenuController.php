<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use App\Models\Template;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class MenuController extends Controller
{
    public function index(): Response
    {
        $this->authorizeMenuManagement();

        return Inertia::render('Menu/Index', [
            'menus' => Menu::query()
                ->with(['template', 'categories.products.variants', 'categories.products.modifiers'])
                ->latest()
                ->get(),
            'templates' => Template::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeMenuManagement();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'template_key' => ['required', 'string', 'exists:templates,key'],
        ]);

        $template = Template::query()
            ->where('key', $data['template_key'])
            ->where('is_active', true)
            ->firstOrFail();

        $restaurant = app(TenantContext::class)->restaurant();

        $theme = $template->key === 'fast-food'
            ? [
                'primary' => '#c9953c',
                'accent' => '#e7b55d',
                'background' => '#090807',
                'foreground' => '#f8efe0',
                'radius' => 'xl',
            ]
            : [
                'primary' => '#111827',
                'accent' => '#f59e0b',
                'background' => '#ffffff',
                'foreground' => '#111827',
                'radius' => 'xl',
            ];

        $menu = Menu::create([
            'restaurant_id' => $restaurant->id,
            'template_id' => $template->id,
            'template_version_id' => $template->activeVersion()->firstOrFail()->id,
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['name']),
            'theme' => $theme,
        ]);

        return to_route('menus.show', $menu);
    }

    public function show(int $menu): Response
    {
        $this->authorizeMenuManagement();

        $menuModel = Menu::query()
            ->with(['template.activeVersion', 'templateVersion', 'categories.products.variants', 'categories.products.modifiers'])
            ->findOrFail($menu);

        return Inertia::render('Menu/Builder', [
            'menu' => $menuModel,
            'template' => $menuModel->template,
            'templates' => Template::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(Request $request, int $menu): RedirectResponse
    {
        $this->authorizeMenuManagement();

        $menuModel = Menu::query()->findOrFail($menu);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'template_key' => ['required', 'string', 'exists:templates,key'],
        ]);

        $template = Template::query()
            ->where('key', $data['template_key'])
            ->where('is_active', true)
            ->firstOrFail();

        $templateChanged = $menuModel->template_id !== $template->id;

        $menuModel->update([
            'name' => $data['name'],
            'template_id' => $template->id,
            'template_version_id' => $templateChanged
                ? $template->activeVersion()->firstOrFail()->id
                : $menuModel->template_version_id,
            'slug' => $menuModel->name !== $data['name']
                ? $this->uniqueSlug($data['name'], $menuModel->id)
                : $menuModel->slug,
        ]);

        return to_route('menus.show', $menuModel);
    }

    public function updateTheme(Request $request, int $menu): RedirectResponse
    {
        $this->authorizeMenuManagement();

        $menuModel = Menu::query()->findOrFail($menu);

        $data = $request->validate([
            'primary' => ['required', 'string', 'size:7'],
            'accent' => ['required', 'string', 'size:7'],
            'background' => ['required', 'string', 'size:7'],
            'foreground' => ['required', 'string', 'size:7'],
            'radius' => ['required', 'in:sm,md,lg,xl,2xl'],
        ]);

        foreach (['primary', 'accent', 'background', 'foreground'] as $color) {
            abort_unless(
                preg_match('/^#[0-9A-Fa-f]{6}$/', $data[$color]) === 1,
                422,
                'Invalid color token.'
            );
        }

        $menuModel->update(['theme' => $data]);

        return to_route('menus.show', $menuModel);
    }

    public function publish(int $menu): RedirectResponse
    {
        $this->authorizeMenuManagement();

        $menuModel = Menu::query()
            ->with(['templateVersion', 'categories.products'])
            ->findOrFail($menu);

        $errors = $this->publishValidationErrors($menuModel);

        if ($errors !== []) {
            return to_route('menus.show', $menuModel)->withErrors($errors);
        }

        $menuModel->update(['is_published' => true]);

        return to_route('menus.show', $menuModel)->with('success', 'Menu published successfully.');
    }

    public function unpublish(int $menu): RedirectResponse
    {
        $this->authorizeMenuManagement();

        $menuModel = Menu::query()->findOrFail($menu);
        $menuModel->update(['is_published' => false]);

        return to_route('menus.show', $menuModel)->with('success', 'Menu unpublished successfully.');
    }

    public function destroy(int $menu): RedirectResponse
    {
        $this->authorizeMenuManagement();

        $menuModel = Menu::query()->findOrFail($menu);
        $menuModel->delete();

        return to_route('menus.index');
    }

    private function publishValidationErrors(Menu $menu): array
    {
        $errors = [];

        if (!$menu->template_id) {
            $errors['publish'] = 'اختر Template قبل نشر المنيو.';
        }

        if (!$menu->templateVersion) {
            $errors['publish'] = 'الـTemplate Version غير متاح. افتح المنيو واحفظها مرة أخرى.';
        }

        $activeCategories = $menu->categories->where('is_active', true);

        if ($activeCategories->isEmpty()) {
            $errors['publish'] = 'لا يمكن النشر بدون قسم واحد نشط على الأقل.';
        }

        $hasAvailableProduct = $activeCategories
            ->flatMap(fn ($category) => $category->products)
            ->contains(fn ($product) => (bool) $product->is_available);

        if (!$hasAvailableProduct) {
            $errors['publish'] = 'لا يمكن النشر بدون منتج واحد متاح على الأقل داخل قسم نشط.';
        }

        return $errors;
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

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'menu';
        $slug = $base;

        $query = Menu::query()
            ->withoutGlobalScopes()
            ->where('restaurant_id', app(TenantContext::class)->id());

        if ($ignoreId !== null) {
            $query->where('id', '<>', $ignoreId);
        }

        $suffix = 2;

        while ($query->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $suffix++;
        }

        return $slug;
    }
}
