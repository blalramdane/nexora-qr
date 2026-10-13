<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use App\Models\RestaurantTable;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RestaurantTableController extends Controller
{
    public function index(): Response
    {
        $restaurant = app(TenantContext::class)->restaurant();
        $this->authorizeManagement($restaurant);

        $menus = Menu::query()->where('restaurant_id', $restaurant->id)->where('is_published', true)
            ->orderBy('name')->get(['id', 'name', 'slug'])
            ->map(fn (Menu $menu): array => [
                'id' => $menu->id,
                'name' => $menu->name,
                'url' => route('public.menu.show', [$restaurant->slug, $menu->slug]),
            ])->values();

        $tables = RestaurantTable::query()->where('restaurant_id', $restaurant->id)->orderBy('name')->get()
            ->map(fn (RestaurantTable $table): array => [
                'id' => $table->id,
                'name' => $table->name,
                'is_active' => $table->is_active,
                'token' => Crypt::decryptString($table->qr_token_encrypted),
            ])->values();

        $managementBase = request()->routeIs('owner.restaurants.tables.*')
            ? route('owner.restaurants.tables.index', ['restaurant' => $restaurant->id])
            : route('tables.index');

        return Inertia::render('Tables/Index', [
            'tables' => $tables,
            'menus' => $menus,
            'managementBase' => $managementBase,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $restaurant = app(TenantContext::class)->restaurant();
        $this->authorizeManagement($restaurant);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('restaurant_tables', 'name')->where('restaurant_id', $restaurant->id)],
        ]);
        $name = trim($data['name']);
        abort_if($name === '', 422, 'اكتب اسم الترابيزة.');
        abort_if(RestaurantTable::query()->where('restaurant_id', $restaurant->id)->where('name', $name)->exists(), 422, 'اسم الترابيزة مستخدم بالفعل في المطعم.');
        $token = Str::random(64);

        RestaurantTable::query()->create([
            'restaurant_id' => $restaurant->id,
            'name' => $name,
            'qr_token_hash' => hash('sha256', $token),
            'qr_token_encrypted' => Crypt::encryptString($token),
            'is_active' => true,
        ]);

        return redirect($this->indexUrl())->with('success', 'تمت إضافة الترابيزة وإنشاء رمز QR آمن لها.');
    }

    public function update(Request $request, int $table): RedirectResponse
    {
        $restaurant = app(TenantContext::class)->restaurant();
        $this->authorizeManagement($restaurant);
        $record = RestaurantTable::query()->where('restaurant_id', $restaurant->id)->findOrFail($table);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('restaurant_tables', 'name')->where('restaurant_id', $restaurant->id)->ignore($record->id)],
            'is_active' => ['required', 'boolean'],
        ]);
        $name = trim($data['name']);
        abort_if($name === '', 422, 'اكتب اسم الترابيزة.');
        abort_if(RestaurantTable::query()->where('restaurant_id', $restaurant->id)->where('name', $name)->where('id', '<>', $record->id)->exists(), 422, 'اسم الترابيزة مستخدم بالفعل في المطعم.');
        $record->update(['name' => $name, 'is_active' => $data['is_active']]);

        return redirect($this->indexUrl())->with('success', $data['is_active'] ? 'تم تحديث الترابيزة وتفعيل QR.' : 'تم إيقاف الترابيزة؛ لن تقبل طلبات جديدة من QR الخاص بها.');
    }

    private function indexUrl(): string
    {
        $restaurant = app(TenantContext::class)->restaurant();
        return request()->routeIs('owner.restaurants.tables.*')
            ? route('owner.restaurants.tables.index', ['restaurant' => $restaurant->id])
            : route('tables.index');
    }

    private function authorizeManagement(\App\Models\Restaurant $restaurant): void
    {
        $user = request()->user();
        abort_unless($user?->isPlatformOwner() || $user?->isOwnerOf($restaurant) || $user?->roleIn($restaurant) === 'manager', 403);
    }
}
