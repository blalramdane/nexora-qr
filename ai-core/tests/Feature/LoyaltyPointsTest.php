<?php

namespace Tests\Feature;

use App\Models\LoyaltyPoint;
use App\Models\Restaurant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoyaltyPointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_loyalty_points_can_link_an_optional_customer_user_or_stay_phone_only(): void
    {
        $restaurant = Restaurant::create([
            'name' => 'Loyalty Restaurant',
            'slug' => 'loyalty-restaurant',
            'is_active' => true,
        ]);
        app(TenantContext::class)->set($restaurant);

        $user = User::factory()->create();
        $linked = LoyaltyPoint::create([
            'restaurant_id' => $restaurant->id,
            'customer_id' => $user->id,
            'phone' => '01011111111',
        ]);
        $phoneOnly = LoyaltyPoint::create([
            'restaurant_id' => $restaurant->id,
            'customer_id' => null,
            'phone' => '01022222222',
        ]);

        $this->assertSame($user->id, $linked->customer?->id);
        $this->assertNull($phoneOnly->customer);
        $this->assertDatabaseHas('loyalty_points', [
            'restaurant_id' => $restaurant->id,
            'phone' => '01022222222',
            'customer_id' => null,
        ]);
    }
}
