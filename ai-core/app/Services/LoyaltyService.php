<?php

namespace App\Services;

use App\Models\LoyaltyPoint;
use App\Models\LoyaltyTransaction;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class LoyaltyService
{
    public const EARN_RATE = 10; // points per 100 currency units

    public const REDEEM_VALUE = 0.01; // currency per point

    public function earnForOrder(Order $order): ?LoyaltyTransaction
    {
        $phone = $order->customer_phone;
        if (! $phone) {
            return null;
        }

        $points = (int) floor(((float) $order->total) / 100 * self::EARN_RATE);
        if ($points <= 0) {
            return null;
        }

        return DB::transaction(function () use ($order, $points) {
            $lp = LoyaltyPoint::firstOrCreate(
                ['restaurant_id' => $order->restaurant_id, 'phone' => $phone],
            );
            $lp->increment('points_balance', $points);
            $lp->increment('lifetime_earned', $points);
            $lp->save();

            return LoyaltyTransaction::create([
                'restaurant_id' => $order->restaurant_id,
                'loyalty_point_id' => $lp->id,
                'order_id' => $order->id,
                'type' => 'earn',
                'points' => $points,
                'description' => 'Earned from order #'.$order->id,
            ]);
        });
    }

    public function redeem(LoyaltyPoint $lp, int $points, ?Order $order = null): LoyaltyTransaction
    {
        if ($points > $lp->points_balance) {
            throw new \RuntimeException('Insufficient points');
        }

        return DB::transaction(function () use ($lp, $points, $order) {
            $lp->decrement('points_balance', $points);
            $lp->increment('lifetime_redeemed', $points);

            return LoyaltyTransaction::create([
                'restaurant_id' => $lp->restaurant_id,
                'loyalty_point_id' => $lp->id,
                'order_id' => $order?->id,
                'type' => 'redeem',
                'points' => -$points,
                'description' => 'Redeemed '.$points.' points',
            ]);
        });
    }
}
