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
        return Inertia::render('Menu/Index', [
            'menus' => Menu::query()->with(['template', 'categories.products.variants'])->latest()->get(),
            'templates' => Template::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'template_key' => ['required', 'string', 'exists:templates,key'],
        ]);

        $template = Template::query()->where('key', $data['template_key'])->where('is_active', true)->firstOrFail();
        $restaurant = app(TenantContext::class)->restaurant();

        $menu = Menu::create([
            'restaurant_id' => $restaurant->id,
            'template_id' => $template->id,
            'name' => $data['name'],
            'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(5)),
            'theme' => ['primary'=>'#111827','accent'=>'#f59e0b','background'=>'#ffffff','foreground'=>'#111827','radius'=>'xl'],
        ]);

        return to_route('menus.show', $menu);
    }

    public function show(Menu $menu): Response
    {
        $menu->load(['template.activeVersion', 'categories.products.variants']);

        return Inertia::render('Menu/Builder', ['menu' => $menu, 'template' => $menu->template]);
    }
}
