<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use App\Models\Template;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $version = $template->activeVersion()->firstOrFail();
        $theme = $version->schema['theme'] ?? null;
        if (! is_array($theme)) {
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
        }

        $menu = Menu::create([
            'restaurant_id' => $restaurant->id,
            'template_id' => $template->id,
            'template_version_id' => $version->id,
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['name']),
            'theme' => $theme,
        ]);

        return to_route('menus.show', $menu);
    }

    public function storeForOwnerStudio(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'restaurant_id' => ['required', 'integer', 'exists:restaurants,id'],
            'name' => ['required', 'string', 'max:120'],
            'template_key' => ['required', 'string', 'exists:templates,key'],
        ]);

        $restaurant = \App\Models\Restaurant::query()->findOrFail($data['restaurant_id']);
        abort_unless(
            $request->user()?->isPlatformOwner()
                || in_array($request->user()?->roleIn($restaurant), ['owner', 'manager'], true),
            403
        );
        app(TenantContext::class)->set($restaurant);
        $template = Template::query()->where('key', $data['template_key'])->where('is_active', true)->firstOrFail();
        $version = $template->activeVersion()->firstOrFail();
        $theme = $version->schema['theme'] ?? [
            'primary' => '#111827', 'accent' => '#f59e0b', 'background' => '#ffffff', 'foreground' => '#111827', 'radius' => 'xl',
        ];
        $baseSlug = Str::slug($data['name']) ?: 'menu';
        // Query the table directly so tenant global scopes cannot hide a
        // duplicate belonging to this restaurant and cause a UNIQUE failure.
        $slug = $baseSlug;
        $suffix = 2;
        while (DB::table('menus')->where('restaurant_id', $restaurant->id)->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$suffix++;
        }

        $menu = Menu::create([
            'restaurant_id' => $restaurant->id,
            'template_id' => $template->id,
            'template_version_id' => $version->id,
            'name' => $data['name'],
            'slug' => $slug,
            'theme' => $theme,
        ]);

        return to_route('owner.menus.show', $menu)->with('success', 'تم إنشاء المنيو بالقالب المختار للمطعم.');
    }

    public function show(int $menu): Response
    {
        $this->authorizeMenuManagement();

        $menuModel = Menu::query()
            ->with(['restaurant:id,name', 'template.activeVersion', 'templateVersion', 'categories.products.variants', 'categories.products.modifiers'])
            ->findOrFail($menu);

        return Inertia::render('Menu/Builder', [
            'menu' => $menuModel,
            'restaurantName' => $menuModel->restaurant?->name ?? 'NEXORA',
            'publicUrl' => route('public.menu.show', [app(TenantContext::class)->restaurant()->slug, $menuModel->slug]),
            'template' => $menuModel->template,
            'managementBase' => request()->routeIs('owner.*') ? '/owner/menus/'.$menuModel->id : '/menus/'.$menuModel->id,
            'menusIndexUrl' => request()->routeIs('owner.*') ? route('owner.menus.index') : route('menus.index'),
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

        return to_route(request()->routeIs('owner.*') ? 'owner.menus.show' : 'menus.show', $menuModel);
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

        return to_route(request()->routeIs('owner.*') ? 'owner.menus.show' : 'menus.show', $menuModel);
    }

    public function publish(int $menu): RedirectResponse
    {
        $this->authorizeMenuManagement();

        $menuModel = Menu::query()
            ->with(['templateVersion', 'categories.products'])
            ->findOrFail($menu);

        $errors = $this->publishValidationErrors($menuModel);

        if ($errors !== []) {
            return redirect()->route(request()->routeIs('owner.*') ? 'owner.menus.show' : 'menus.show', ['menu' => $menuModel->id])->withErrors($errors);
        }

        $menuModel->update(['is_published' => true]);
        return to_route(request()->routeIs('owner.*') ? 'owner.menus.show' : 'menus.show', $menuModel)->with('success', 'Menu published successfully.');
    }

    public function unpublish(int $menu): RedirectResponse
    {
        $this->authorizeMenuManagement();

        $menuModel = Menu::query()->findOrFail($menu);
        $menuModel->update(['is_published' => false]);
        return to_route(request()->routeIs('owner.*') ? 'owner.menus.show' : 'menus.show', $menuModel)->with('success', 'Menu unpublished successfully.');
    }

    public function destroy(int $menu): RedirectResponse
    {
        $this->authorizeMenuManagement();

        $menuModel = Menu::query()->findOrFail($menu);
        $menuModel->delete();

        return to_route(request()->routeIs('owner.*') ? 'owner.menus.index' : 'menus.index');
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
            $user?->isPlatformOwner() || $user?->isOwnerOf($restaurant) || $user?->roleIn($restaurant) === 'manager',
            403
        );
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'menu';
        $slug = $base;

        $suffix = 2;

        while (Menu::query()
            ->withoutGlobalScopes()
            ->where('restaurant_id', app(TenantContext::class)->id())
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '<>', $ignoreId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = $base . '-' . $suffix++;
        }

        return $slug;
    }
}
