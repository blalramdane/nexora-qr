<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Restaurant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_restaurant_owner_can_create_pause_and_delete_own_coupon(): void
    {
        $restaurant = Restaurant::create(['name' => 'Coupon Owner', 'slug' => 'coupon-owner', 'is_active' => true]);
        app(TenantContext::class)->set($restaurant);
        $owner = User::factory()->create();
        $owner->restaurants()->attach($restaurant->id, ['role' => 'owner']);

        $this->actingAs($owner)->post(route('coupons.store'), [
            'code' => 'welcome10', 'type' => 'percent', 'value' => 10, 'minimum_subtotal' => 100,
            'maximum_discount' => 50, 'usage_limit' => 20, 'starts_at' => null, 'ends_at' => null, 'is_active' => 1,
        ])->assertRedirect(route('coupons.index'));
        $coupon = Coupon::query()->where('restaurant_id', $restaurant->id)->firstOrFail();
        $this->assertSame('WELCOME10', $coupon->code);

        $this->actingAs($owner)->put(route('coupons.update', $coupon->id), [
            'code' => 'WELCOME10', 'type' => 'percent', 'value' => 10, 'minimum_subtotal' => 100,
            'maximum_discount' => 50, 'usage_limit' => 20, 'starts_at' => null, 'ends_at' => null, 'is_active' => 0,
        ])->assertRedirect(route('coupons.index'));
        $this->assertFalse($coupon->fresh()->is_active);

        $this->actingAs($owner)->delete(route('coupons.destroy', $coupon->id))->assertRedirect(route('coupons.index'));
        $this->assertDatabaseMissing('coupons', ['id' => $coupon->id]);
    }

    public function test_restaurant_cannot_manage_another_restaurants_coupon(): void
    {
        $restaurant = Restaurant::create(['name' => 'First Restaurant', 'slug' => 'first-coupon-restaurant', 'is_active' => true]);
        $otherRestaurant = Restaurant::create(['name' => 'Second Restaurant', 'slug' => 'second-coupon-restaurant', 'is_active' => true]);
        app(TenantContext::class)->set($restaurant);
        $owner = User::factory()->create();
        $owner->restaurants()->attach($restaurant->id, ['role' => 'owner']);
        $coupon = Coupon::create(['restaurant_id' => $otherRestaurant->id, 'code' => 'PRIVATE10', 'type' => 'fixed', 'value' => 10, 'is_active' => true]);

        $this->actingAs($owner)->put(route('coupons.update', $coupon->id), [
            'code' => 'PRIVATE10', 'type' => 'fixed', 'value' => 10, 'minimum_subtotal' => 0,
            'maximum_discount' => null, 'usage_limit' => null, 'starts_at' => null, 'ends_at' => null, 'is_active' => 0,
        ])->assertNotFound();
        $this->assertTrue($coupon->fresh()->is_active);
    }
}
