<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_throttling_returns_a_login_form_error_instead_of_a_429_error_page(): void
    {
        $credentials = ['email' => 'missing-user@example.test', 'password' => 'not-the-password'];

        foreach (range(1, 5) as $attempt) {
            $this->post(route('login.store'), $credentials)->assertRedirect();
        }

        $this->post(route('login.store'), $credentials)
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'محاولات تسجيل الدخول كثيرة. انتظر دقيقة ثم حاول مرة أخرى.']);
    }

    public function test_authenticated_non_rider_owner_is_redirected_to_the_restaurant_dashboard_not_a_404(): void
    {
        $restaurant = Restaurant::create([
            'name' => 'Auth Recovery Restaurant',
            'slug' => 'auth-recovery-restaurant',
            'is_active' => true,
        ]);
        $owner = User::factory()->create();
        $owner->restaurants()->attach($restaurant->id, ['role' => 'owner']);

        $this->actingAs($owner)
            ->get(route('delivery.dashboard'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_rider_dashboard_responses_are_not_cached_by_the_browser(): void
    {
        $user = User::factory()->create();
        $user->deliveryRider()->create([
            'phone' => '01012345678',
            'vehicle_type' => 'motorcycle',
            'coverage_areas' => ['الزرقا'],
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->get(route('delivery.dashboard'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'must-revalidate, no-cache, no-store, private');
    }
    public function test_guest_opening_delivery_dashboard_is_redirected_to_login(): void
    {
        $this->get(route('delivery.dashboard'))
            ->assertRedirect(route('login'));
    }
}