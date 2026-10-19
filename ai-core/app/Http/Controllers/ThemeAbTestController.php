<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use App\Models\ThemeAbTest;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ThemeAbTestController extends Controller
{
    private const STYLES = ['modern', 'luxury', 'playful', 'classic'];

    public function index(Request $request): Response
    {
        $restaurant = app(TenantContext::class)->restaurant();
        $tests = ThemeAbTest::query()
            ->where('restaurant_id', $restaurant->id)
            ->with('menu:id,name,slug,is_published')
            ->latest('id')
            ->get();

        return Inertia::render('Owner/ThemeTests', [
            'restaurant' => ['id' => $restaurant->id, 'name' => $restaurant->name],
            'menus' => Menu::query()
                ->where('restaurant_id', $restaurant->id)
                ->where('is_published', true)
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'is_published']),
            'tests' => $tests->map(fn (ThemeAbTest $test): array => [
                'id' => $test->id,
                'menu_id' => $test->menu_id,
                'menu_name' => $test->menu?->name,
                'variant_a' => $test->variant_a,
                'variant_b' => $test->variant_b,
                'status' => $test->status,
                'traffic_split' => $test->traffic_split,
                'impressions_a' => $test->impressions_a,
                'impressions_b' => $test->impressions_b,
                'conversions_a' => $test->conversions_a,
                'conversions_b' => $test->conversions_b,
                'conversion_rate_a' => $test->impressions_a > 0 ? round($test->conversions_a / $test->impressions_a * 100, 2) : 0,
                'conversion_rate_b' => $test->impressions_b > 0 ? round($test->conversions_b / $test->impressions_b * 100, 2) : 0,
                'started_at' => $test->started_at?->toIso8601String(),
                'ended_at' => $test->ended_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $restaurant = app(TenantContext::class)->restaurant();
        $data = $request->validate([
            'menu_id' => ['required', 'integer'],
            'variant_a' => ['required', 'string', 'in:'.implode(',', self::STYLES)],
            'variant_b' => ['required', 'string', 'in:'.implode(',', self::STYLES), 'different:variant_a'],
            'traffic_split' => ['required', 'integer', 'between:0,100'],
        ]);

        $menu = Menu::query()->where('restaurant_id', $restaurant->id)->whereKey($data['menu_id'])->firstOrFail();
        if (! $menu->is_published) {
            throw ValidationException::withMessages(['menu_id' => 'انشر المنيو قبل تشغيل اختبار A/B عليها.']);
        }

        DB::transaction(function () use ($restaurant, $menu, $data): void {
            $lockedMenu = Menu::query()->where('restaurant_id', $restaurant->id)->whereKey($menu->id)->lockForUpdate()->firstOrFail();
            $runningTestExists = ThemeAbTest::query()
                ->where('restaurant_id', $restaurant->id)
                ->where('menu_id', $lockedMenu->id)
                ->where('status', 'running')
                ->exists();

            if ($runningTestExists) {
                throw ValidationException::withMessages(['menu_id' => 'يوجد اختبار A/B شغال بالفعل على المنيو دي. أوقفه قبل إنشاء اختبار جديد.']);
            }

            ThemeAbTest::query()->create([
                'restaurant_id' => $restaurant->id,
                'menu_id' => $lockedMenu->id,
                'variant_a' => $data['variant_a'],
                'variant_b' => $data['variant_b'],
                'status' => 'running',
                'traffic_split' => $data['traffic_split'],
                'started_at' => now(),
            ]);
        });

        return to_route('owner.restaurants.theme-tests.index', $restaurant->id)->with('success', 'تم تشغيل اختبار A/B للمنيو.');
    }

    public function update(Request $request): RedirectResponse
    {
        $restaurant = app(TenantContext::class)->restaurant();
        $test = ThemeAbTest::query()
            ->where('restaurant_id', $restaurant->id)
            ->findOrFail((int) $request->route('test'));

        if ($test->status === 'completed') {
            abort(409, 'الاختبار المكتمل لا يمكن تعديله أو إعادة تشغيله. أنشئ اختبارًا جديدًا بدلًا منه.');
        }

        $data = $request->validate([
            'status' => ['sometimes', 'required', 'string', 'in:running,paused,completed'],
            'traffic_split' => ['sometimes', 'required', 'integer', 'between:0,100'],
        ]);

        DB::transaction(function () use ($data, $restaurant, $test): void {
            $lockedTest = ThemeAbTest::query()
                ->where('restaurant_id', $restaurant->id)
                ->whereKey($test->id)
                ->lockForUpdate()
                ->firstOrFail();
            $updates = [];
            if (array_key_exists('traffic_split', $data)) {
                $updates['traffic_split'] = $data['traffic_split'];
            }
            if (isset($data['status'])) {
                if ($data['status'] === 'running') {
                    $menu = Menu::query()->where('restaurant_id', $restaurant->id)->whereKey($lockedTest->menu_id)->lockForUpdate()->firstOrFail();
                    $otherRunningTest = ThemeAbTest::query()
                        ->where('restaurant_id', $restaurant->id)
                        ->where('menu_id', $menu->id)
                        ->where('status', 'running')
                        ->where('id', '<>', $lockedTest->id)
                        ->exists();
                    if ($otherRunningTest) {
                        throw ValidationException::withMessages(['status' => 'يوجد اختبار آخر شغال بالفعل على المنيو دي. أوقفه قبل استئناف الاختبار.']);
                    }
                    if (! $lockedTest->started_at) {
                        $updates['started_at'] = now();
                    }
                    $updates['ended_at'] = null;
                }
                $updates['status'] = $data['status'];
                if ($data['status'] === 'completed') {
                    $updates['ended_at'] = now();
                }
            }

            $lockedTest->update($updates);
        });

        return to_route('owner.restaurants.theme-tests.index', $restaurant->id)->with('success', 'تم حفظ إعدادات اختبار A/B.');
    }
}
