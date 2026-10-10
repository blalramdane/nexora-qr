<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CouponController extends Controller
{
    public function index(): Response
    {
        $this->authorizeManagement();
        $coupons = Coupon::query()->where('restaurant_id', app(TenantContext::class)->id())
            ->latest()->get()->map(fn (Coupon $coupon) => [
                'id' => $coupon->id, 'code' => $coupon->code, 'type' => $coupon->type,
                'value' => $coupon->value, 'minimum_subtotal' => $coupon->minimum_subtotal,
                'maximum_discount' => $coupon->maximum_discount, 'usage_limit' => $coupon->usage_limit,
                'usage_count' => $coupon->usage_count, 'starts_at' => $coupon->starts_at?->format('Y-m-d\\TH:i'),
                'ends_at' => $coupon->ends_at?->format('Y-m-d\\TH:i'), 'is_active' => $coupon->is_active,
                'usable_now' => $coupon->isUsableNow(),
            ]);

        return Inertia::render('Coupons/Index', ['coupons' => $coupons]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManagement();
        $data = $this->validatedData($request);
        $data['restaurant_id'] = app(TenantContext::class)->id();
        $data['code'] = Str::upper(trim($data['code']));
        Coupon::create($data);

        return to_route('coupons.index')->with('success', 'تم إنشاء كود الخصم بنجاح.');
    }

    public function update(Request $request, int $coupon): RedirectResponse
    {
        $this->authorizeManagement();
        $record = Coupon::query()->where('restaurant_id', app(TenantContext::class)->id())->findOrFail($coupon);
        $data = $this->validatedData($request, $record->id);
        $data['code'] = Str::upper(trim($data['code']));
        $record->update($data);

        return to_route('coupons.index')->with('success', 'تم تحديث العرض.');
    }

    public function destroy(int $coupon): RedirectResponse
    {
        $this->authorizeManagement();
        Coupon::query()->where('restaurant_id', app(TenantContext::class)->id())->findOrFail($coupon)->delete();

        return to_route('coupons.index')->with('success', 'تم حذف كود الخصم.');
    }

    private function validatedData(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'min:3', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/',
                'unique:coupons,code,' . ($ignoreId ?? 'NULL') . ',id,restaurant_id,' . app(TenantContext::class)->id()],
            'type' => ['required', 'in:percent,fixed'],
            'value' => ['required', 'numeric', 'gt:0', 'max:100000'],
            'minimum_subtotal' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'maximum_discount' => ['nullable', 'numeric', 'gt:0', 'max:1000000', 'required_if:type,percent'],
            'usage_limit' => ['nullable', 'integer', 'min:1', 'max:10000000'],
            'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['required', 'boolean'],
        ], [
            'code.unique' => 'كود الخصم مستخدم بالفعل في مطعمك.',
            'code.regex' => 'استخدم حروف إنجليزية وأرقام وشرطة فقط في الكود.',
            'maximum_discount.required_if' => 'حدد أقصى خصم للعروض بالنسبة المئوية.',
        ]);
        if ($data['type'] === 'percent' && (float) $data['value'] > 100) {
            throw \Illuminate\Validation\ValidationException::withMessages(['value' => 'النسبة المئوية لا يمكن أن تتجاوز 100%.']);
        }

        return $data;
    }

    private function authorizeManagement(): void
    {
        $user = request()->user();
        $restaurant = app(TenantContext::class)->restaurant();
        abort_unless($user?->isOwnerOf($restaurant) || $user?->roleIn($restaurant) === 'manager', 403);
    }
}
