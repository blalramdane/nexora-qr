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
                ->with(['template', 'categories.products.variants'])
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

        $menu = Menu::create([
            'restaurant_id' => $restaurant->id,
            'template_id' => $template->id,
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['name']),
            'theme' => [
                'primary' => '#111827',
                'accent' => '#f59e0b',
                'background' => '#ffffff',
                'foreground' => '#111827',
                'radius' => 'xl',
            ],
        ]);

        return to_route('menus.show', $menu);
    }

    public function show(int $menu): Response
    {
        $this->authorizeMenuManagement();

        $menuModel = Menu::query()
            ->with(['template.activeVersion', 'categories.products.variants'])
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

        $menuModel->update([
            'name' => $data['name'],
            'template_id' => $template->id,
            'slug' => $menuModel->name !== $data['name']
                ? $this->uniqueSlug($data['name'], $menuModel->id)
                : $menuModel->slug,
        ]);

        return to_route('menus.show', $menuModel);
    }

    public function destroy(int $menu): RedirectResponse
    {
        $this->authorizeMenuManagement();

        $menuModel = Menu::query()->findOrFail($menu);
        $menuModel->delete();

        return to_route('menus.index');
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
