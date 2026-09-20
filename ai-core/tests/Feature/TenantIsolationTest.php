<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_session_cannot_select_an_unowned_restaurant(): void
    {
        $user = User::factory()->create();
        $owned = Restaurant::create(['name' => 'Owned', 'slug' => 'owned']);
        $other = Restaurant::create(['name' => 'Other', 'slug' => 'other']);

        $owned->users()->attach($user, ['role' => 'owner', 'is_active' => true]);

        $this->actingAs($user)
            ->withSession(['active_restaurant_id' => $other->id])
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('restaurant.name', 'Owned'));

        $this->assertSame($owned->id, session('active_restaurant_id'));
    }
}