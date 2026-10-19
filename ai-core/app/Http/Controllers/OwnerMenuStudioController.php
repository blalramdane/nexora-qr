<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use App\Models\Restaurant;
use App\Models\Template;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class OwnerMenuStudioController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $restaurants = $user->isPlatformOwner()
            ? Restaurant::query()->orderBy('name')->get(['id', 'name', 'slug', 'phone', 'is_active'])
            : $user->restaurants()
                ->wherePivotIn('role', ['owner', 'manager'])
                ->orderBy('restaurants.name')
                ->get(['restaurants.id', 'restaurants.name', 'restaurants.slug', 'restaurants.phone', 'restaurants.is_active']);

        $restaurantIds = $restaurants->pluck('id')->map(fn ($id) => (int) $id)->all();
        $menus = $restaurantIds === []
            ? collect()
            : Menu::query()
                ->withoutGlobalScopes()
                ->whereIn('restaurant_id', $restaurantIds)
                ->with(['template:id,key,name', 'restaurant:id,slug'])
                ->latest('updated_at')
                ->get(['id', 'restaurant_id', 'name', 'slug', 'template_id', 'is_published', 'updated_at']);

        $menuIds = $menus->pluck('id')->map(fn ($id) => (int) $id)->all();
        $categoryCounts = $menuIds === [] ? collect() : DB::table('menu_categories')
            ->whereIn('menu_id', $menuIds)
            ->select('menu_id')->selectRaw('COUNT(*) AS total')
            ->groupBy('menu_id')->pluck('total', 'menu_id');
        $productCounts = $menuIds === [] ? collect() : DB::table('menu_categories')
            ->join('products', 'products.menu_category_id', '=', 'menu_categories.id')
            ->whereIn('menu_categories.menu_id', $menuIds)
            ->select('menu_categories.menu_id')->selectRaw('COUNT(products.id) AS total')
            ->groupBy('menu_categories.menu_id')->pluck('total', 'menu_categories.menu_id');

        $menusByRestaurant = $menus->map(function (Menu $menu) use ($categoryCounts, $productCounts): array {
            return [
                'id' => $menu->id,
                'restaurant_id' => $menu->restaurant_id,
                'name' => $menu->name,
                'slug' => $menu->slug,
                'is_published' => (bool) $menu->is_published,
                'updated_at' => $menu->updated_at?->toIso8601String(),
                'template' => $menu->template ? ['key' => $menu->template->key, 'name' => $menu->template->name] : null,
                'categories_count' => (int) ($categoryCounts[$menu->id] ?? 0),
                'products_count' => (int) ($productCounts[$menu->id] ?? 0),
                'public_url' => route('public.menu.show', [$menu->restaurant?->slug, $menu->slug]),
            ];
        })->groupBy('restaurant_id');

        return Inertia::render('Owner/MenuStudio', [
            'platformOwner' => $user->isPlatformOwner(),
            'restaurants' => $restaurants->map(fn (Restaurant $restaurant): array => [
                'id' => $restaurant->id,
                'name' => $restaurant->name,
                'slug' => $restaurant->slug,
                'phone' => $restaurant->phone,
                'is_active' => (bool) $restaurant->is_active,
                'role' => $user->isPlatformOwner() ? 'platform_owner' : $user->roleIn($restaurant),
                'settings_url' => route('owner.restaurants.settings.orders.edit', $restaurant),
                'orders_url' => route('owner.restaurants.orders.index', $restaurant),
                'kds_url' => route('owner.restaurants.kds', $restaurant),
                'tables_url' => route('owner.restaurants.tables.index', $restaurant),
                'theme_tests_url' => route('owner.restaurants.theme-tests.index', $restaurant),
                'menus' => ($menusByRestaurant->get($restaurant->id) ?? collect())->values()->all(),
            ])->values(),
            'templates' => Template::query()
                ->where('is_active', true)
                ->with('activeVersion')
                ->orderBy('name')
                ->get(['id', 'key', 'name', 'description']),
        ]);
    }

    public function storeRestaurant(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isPlatformOwner(), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^[0-9+() .-]{7,32}$/'],
            'whatsapp_phone' => ['nullable', 'string', 'max:32', 'regex:/^[0-9+() .-]{7,32}$/'],
            'address' => ['nullable', 'string', 'max:500'],
            'owner_name' => ['required', 'string', 'max:120'],
            'owner_email' => ['required', 'email', 'max:255'],
        ]);

        [$restaurant, $owner, $needsPasswordSetup] = DB::transaction(function () use ($data): array {
            $baseSlug = Str::slug($data['name']) ?: 'restaurant';
            $slug = $baseSlug;
            $suffix = 2;
            while (Restaurant::query()->where('slug', $slug)->exists()) {
                $slug = $baseSlug.'-'.$suffix++;
            }

            $restaurant = Restaurant::query()->create([
                'name' => trim($data['name']),
                'slug' => $slug,
                'phone' => $data['phone'] ?? null,
                'whatsapp_phone' => $data['whatsapp_phone'] ?? null,
                'is_active' => true,
            ]);

            $restaurant->branches()->create([
                'name' => 'الفرع الرئيسي',
                'slug' => 'main',
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'supported_order_types' => ['dine_in', 'pickup', 'delivery'],
                'is_active' => true,
            ]);

            $ownerEmail = mb_strtolower(trim($data['owner_email']));
            $owner = User::query()->whereRaw('LOWER(email) = ?', [$ownerEmail])->first();

            if ($owner?->isPlatformOwner()) {
                abort(422, 'حساب مالك المنصة لا يمكن استخدامه كحساب مالك مطعم.');
            }

            $needsPasswordSetup = false;
            if (! $owner) {
                $owner = User::query()->create([
                    'name' => trim($data['owner_name']),
                    'email' => $ownerEmail,
                    'password' => Str::random(64),
                ]);
                $needsPasswordSetup = true;
            }

            if ($restaurant->users()->whereKey($owner->id)->exists()) {
                $restaurant->users()->updateExistingPivot($owner->id, ['role' => 'owner']);
            } else {
                $restaurant->users()->attach($owner->id, ['role' => 'owner']);
            }

            return [$restaurant, $owner, $needsPasswordSetup];
        });

        $redirect = to_route('owner.menus.index');

        if (! $needsPasswordSetup) {
            return $redirect->with('success', "تم إنشاء المطعم وربط حساب {$owner->email} كمالك للمطعم. الحساب الموجود احتفظ بكلمة سره الحالية.");
        }

        if (in_array(config('mail.default'), ['log', 'array'], true)) {
            Log::warning('NEXORA restaurant owner created without a delivered setup email because the mailer is not configured for delivery.', [
                'restaurant_id' => $restaurant->id,
                'user_id' => $owner->id,
                'mail_driver' => config('mail.default'),
            ]);

            return $redirect->with('warning', 'تم إنشاء المطعم وحساب صاحبه، لكن إعداد البريد الحالي لا يرسل رسائل فعلية. اضبط البريد من إعدادات السيرفر، ثم استخدم «نسيت كلمة السر» لإرسال رابط تعيين آمن.');
        }

        try {
            $status = Password::sendResetLink(['email' => $owner->email]);
        } catch (\Throwable $exception) {
            Log::error('NEXORA restaurant owner password setup email failed.', [
                'restaurant_id' => $restaurant->id,
                'user_id' => $owner->id,
                'exception' => $exception::class,
            ]);

            return $redirect->with('warning', 'تم إنشاء المطعم وحساب صاحبه، لكن إرسال رابط تعيين كلمة السر تعذر. راجع إعدادات البريد في السيرفر قبل تسليم الحساب.');
        }

        if ($status !== Password::RESET_LINK_SENT) {
            Log::warning('NEXORA restaurant owner password setup link was not sent.', [
                'restaurant_id' => $restaurant->id,
                'user_id' => $owner->id,
                'status' => $status,
            ]);

            return $redirect->with('warning', 'تم إنشاء المطعم وحساب صاحبه، لكن رابط تعيين كلمة السر لم يُرسل. راجع إعدادات البريد وحدود إعادة الإرسال.');
        }

        return $redirect->with('success', 'تم إنشاء المطعم وحساب صاحبه وربطه بالمنيوهات. بعتنا له رابطًا آمنًا على البريد لتعيين كلمة السر والدخول.');
    }

    public function storeTemplate(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isPlatformOwner(), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'layout' => ['required', 'in:fast-food,cafe,fine-dining'],
            'primary' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'accent' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'background' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'foreground' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'radius' => ['required', 'in:sm,md,lg,xl,2xl'],
        ]);

        DB::transaction(function () use ($data): void {
            $baseKey = Str::slug($data['name']) ?: 'custom-template';
            $key = $baseKey.'-'.Str::lower(Str::random(6));
            while (Template::query()->where('key', $key)->exists()) {
                $key = $baseKey.'-'.Str::lower(Str::random(6));
            }

            $template = Template::query()->create([
                'key' => $key,
                'name' => trim($data['name']),
                'description' => trim((string) ($data['description'] ?? '')) ?: null,
                'category' => $data['layout'] === 'cafe' ? 'cafe' : 'restaurant',
                'is_premium' => false,
                'is_active' => true,
            ]);

            $components = match ($data['layout']) {
                'cafe' => ['sticky_header', 'hero', 'category_nav', 'product_card', 'cart_bar', 'product_configurator'],
                'fine-dining' => ['minimal_header', 'hero', 'editorial_product_list', 'cart_bar', 'product_configurator'],
                default => ['sticky_header', 'hero', 'category_nav', 'product_card', 'cart_bar', 'product_configurator'],
            };

            $template->createVersion([
                'layout' => $data['layout'],
                'components' => $components,
                'theme' => [
                    'primary' => strtoupper($data['primary']),
                    'accent' => strtoupper($data['accent']),
                    'background' => strtoupper($data['background']),
                    'foreground' => strtoupper($data['foreground']),
                    'radius' => $data['radius'],
                ],
                'visual' => ['direction' => 'rtl', 'mobile_first' => true, 'customizable' => true],
            ], true);
        });

        return to_route('owner.menus.index')->with('success', 'تم حفظ قالب التصميم، وبقى متاحًا لاستخدامه في منيوهات المطاعم.');
    }

    public function storeMenu(Request $request, int $restaurant): RedirectResponse
    {
        $tenant = app(TenantContext::class)->restaurant();
        abort_unless($tenant->id === $restaurant, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'template_key' => ['required', 'string', 'exists:templates,key'],
        ]);

        $template = Template::query()
            ->where('key', $data['template_key'])
            ->where('is_active', true)
            ->firstOrFail();
        $version = $template->activeVersion()->firstOrFail();

        $baseSlug = Str::slug($data['name']) ?: 'menu';
        $slug = $baseSlug;
        $suffix = 2;
        while (DB::table('menus')->where('restaurant_id', $tenant->id)->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$suffix++;
        }

        $menu = Menu::create([
            'restaurant_id' => $tenant->id,
            'template_id' => $template->id,
            'template_version_id' => $version->id,
            'name' => $data['name'],
            'slug' => $slug,
            'theme' => $version->schema['theme'] ?? [
                'primary' => '#111827', 'accent' => '#D6A85F', 'background' => '#ffffff', 'foreground' => '#111827', 'radius' => 'xl',
            ],
        ]);

        return to_route('owner.menus.show', $menu)->with('success', 'تم إنشاء المنيو؛ تقدر تضيف الأقسام والأصناف والصور والأسعار من المحرر.');
    }
}
