<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_a_restaurant(): void
    {
        $response = $this->post('/register', [
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'restaurant_name' => 'Nexora Demo',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('restaurants', ['name' => 'Nexora Demo']);
        $this->assertDatabaseHas('restaurant_users', ['role' => 'owner']);
        $this->assertDatabaseHas('branches', ['name' => 'Main Branch']);
    }

    public function test_user_can_login_and_logout(): void
    {
        $user = User::factory()->create(['password' => 'Password123!']);
        $restaurant = Restaurant::create(['name' => 'Demo', 'slug' => 'demo']);
        $restaurant->users()->attach($user, ['role' => 'owner', 'is_active' => true]);

        $this->post('/login', ['email' => $user->email, 'password' => 'Password123!'])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticated();
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }
}