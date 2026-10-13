<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
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

    public function test_password_reset_link_can_be_used_to_set_a_new_password(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'new.owner@example.com']);
        $this->assertSame(Password::RESET_LINK_SENT, Password::sendResetLink(['email' => $user->email]));
        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
            $token = $notification->token;

            return is_string($token) && $token !== '';
        });
        $this->assertNotNull($token);

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auth/ResetPassword')->where('email', $user->email));

        $newPassword = 'NexoraTest#2026';
        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ])->assertRedirect(route('login'))->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check($newPassword, $user->fresh()->password));
    }

    public function test_forgot_password_does_not_claim_delivery_when_mail_driver_is_log(): void
    {
        Notification::fake();
        config(['mail.default' => 'log']);
        $user = User::factory()->create(['email' => 'mail-log-owner@example.com']);

        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors('email');

        Notification::assertNothingSent();
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_forgot_password_sends_a_reset_notification_when_mail_delivery_is_configured(): void
    {
        Notification::fake();
        config(['mail.default' => 'smtp']);
        $user = User::factory()->create(['email' => 'mail-ready-owner@example.com']);

        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);
    }
}
