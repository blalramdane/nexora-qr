<?php

namespace App\Http\Controllers;

use App\Support\Tenancy\TenantContext;
use App\Services\SyncRestaurantToLocalDirectory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RestaurantSettingsController extends Controller
{
    public function edit(): Response
    {
        $restaurant = app(TenantContext::class)->restaurant();
        $this->authorizeManagement($restaurant);

        return Inertia::render('RestaurantSettings', ['settings' => [
            'name' => $restaurant->name,
            'whatsapp_phone' => $restaurant->whatsapp_phone,
            'phone' => $restaurant->phone,
            'facebook_url' => $restaurant->facebook_url,
            'instagram_url' => $restaurant->instagram_url,
            'tiktok_url' => $restaurant->tiktok_url,
            'website_url' => $restaurant->website_url,
            'delivery_fee' => (string) ($restaurant->delivery_fee ?? '0.00'),
            'delivery_areas' => implode('، ', $restaurant->delivery_areas ?? []),
            'delivery_enabled' => (bool) ($restaurant->delivery_enabled ?? true),
            'pickup_enabled' => (bool) ($restaurant->pickup_enabled ?? true),
            'pos_integration_enabled' => (bool) ($restaurant->pos_integration_enabled ?? false),
            'pos_provider' => $restaurant->pos_provider,
            'pos_endpoint_url' => $restaurant->pos_endpoint_url,
            'pos_integration_notes' => $restaurant->pos_integration_notes,
        ]]);
    }

    public function update(Request $request): RedirectResponse
    {
        $restaurant = app(TenantContext::class)->restaurant();
        $this->authorizeManagement($restaurant);

        $data = $request->validate([
            'whatsapp_phone' => ['nullable', 'string', 'max:32', 'regex:/^[0-9+()\s-]*$/'],
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^[0-9+()\s-]*$/'],
            'facebook_url' => ['nullable', 'url', 'max:500'],
            'instagram_url' => ['nullable', 'url', 'max:500'],
            'tiktok_url' => ['nullable', 'url', 'max:500'],
            'website_url' => ['nullable', 'url', 'max:500'],
            'delivery_fee' => ['required', 'numeric', 'min:0', 'max:100000'],
            'delivery_areas' => ['nullable', 'string', 'max:1000'],
            'delivery_enabled' => ['required', 'boolean'],
            'pickup_enabled' => ['required', 'boolean'],
            'pos_integration_enabled' => ['required', 'boolean'],
            'pos_provider' => ['nullable', 'string', 'max:120'],
            'pos_endpoint_url' => ['nullable', 'url', 'max:500'],
            'pos_integration_notes' => ['nullable', 'string', 'max:2000'],
        ]);
        abort_unless($data['delivery_enabled'] || $data['pickup_enabled'], 422, 'يجب تفعيل طريقة استلام واحدة على الأقل.');
        $data['delivery_areas'] = collect(preg_split('/[,،\n]+/u', $data['delivery_areas'] ?? ''))
            ->map(fn ($area) => trim($area))->filter()->unique()->take(30)->values()->all();
        $restaurant->update($data);
        app(SyncRestaurantToLocalDirectory::class)->sync($restaurant->fresh());

        return to_route('restaurant.settings.edit')->with('success', 'تم حفظ إعدادات الطلبات.');
    }

    private function authorizeManagement($restaurant): void
    {
        $user = request()->user();
        abort_unless($user?->isOwnerOf($restaurant) || $user?->roleIn($restaurant) === 'manager', 403);
    }
}
