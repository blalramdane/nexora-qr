<?php

namespace App\Services;

use App\Models\Menu;
use App\Models\Order;
use App\Models\ThemeAbTest;
use App\Models\ThemeAbTestParticipant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ThemeAbTestingService
{
    public const COOKIE_NAME = 'nexora_ab_visitor';

    /**
     * Assign a stable variant for this browser and count one impression per
     * test/browser pair. Only published-menu requests call this method.
     */
    public function assignFor(Menu $menu, Request $request): ?array
    {
        $test = ThemeAbTest::query()
            ->where('restaurant_id', $menu->restaurant_id)
            ->where('menu_id', $menu->id)
            ->where('status', 'running')
            ->latest('id')
            ->first();

        if (! $test) {
            return null;
        }

        $visitorToken = (string) $request->cookie(self::COOKIE_NAME, '');
        if (! preg_match('/^[A-Za-z0-9]{64}$/', $visitorToken)) {
            $visitorToken = Str::random(64);
            Cookie::queue(cookie(
                self::COOKIE_NAME,
                $visitorToken,
                60 * 24 * 90,
                '/',
                null,
                $request->isSecure(),
                true,
                false,
                'lax',
            ));
        }

        $tokenHash = hash('sha256', $visitorToken);
        $participant = ThemeAbTestParticipant::query()
            ->where('theme_ab_test_id', $test->id)
            ->where('visitor_token_hash', $tokenHash)
            ->first();

        if (! $participant) {
            // Serialize first-time assignment for this test. Existing visitors
            // take the cheap lookup path above without locking the test row.
            $participant = DB::transaction(function () use ($test, $tokenHash): ThemeAbTestParticipant {
                $lockedTest = ThemeAbTest::query()->whereKey($test->id)->lockForUpdate()->firstOrFail();
                $existing = ThemeAbTestParticipant::query()
                    ->where('theme_ab_test_id', $lockedTest->id)
                    ->where('visitor_token_hash', $tokenHash)
                    ->first();

                if ($existing) {
                    return $existing;
                }

                $variant = random_int(1, 100) <= $lockedTest->traffic_split ? 'b' : 'a';
                $participant = ThemeAbTestParticipant::query()->create([
                    'theme_ab_test_id' => $lockedTest->id,
                    'visitor_token_hash' => $tokenHash,
                    'variant' => $variant,
                ]);
                $lockedTest->increment('impressions_'.$variant);

                return $participant;
            });
        }

        return [
            'id' => $test->id,
            'variant' => $participant->variant,
            'style' => $test->{'variant_'.$participant->variant},
        ];
    }

    /** Attribute the first order to the most recent experiment the browser actually saw. */
    public function recordConversion(Order $order, ?string $visitorToken): void
    {
        if (! is_string($visitorToken) || ! preg_match('/^[A-Za-z0-9]{64}$/', $visitorToken)) {
            return;
        }

        // Conversion attribution belongs to the participant record, not the
        // current experiment status. A customer can place an order after an
        // owner completes a test; that order must still count for the variant
        // the customer actually saw. The exposure must predate the order and
        // the restaurant/menu scope must match the order exactly.
        $participant = ThemeAbTestParticipant::query()
            ->where('visitor_token_hash', hash('sha256', $visitorToken))
            ->whereNull('converted_at')
            ->where('created_at', '<=', $order->created_at ?? now())
            ->whereHas('test', function ($query) use ($order): void {
                $query->where('restaurant_id', $order->restaurant_id)
                    ->where('menu_id', $order->menu_id)
                    ->whereNotNull('started_at')
                    ->where('started_at', '<=', $order->created_at ?? now());
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        if (! $participant) {
            return;
        }

        $participant->update(['converted_at' => now(), 'order_id' => $order->id]);
        ThemeAbTest::query()->whereKey($participant->theme_ab_test_id)
            ->increment('conversions_'.$participant->variant);
    }
}
