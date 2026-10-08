<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Restaurant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_queries_are_isolated_to_the_active_tenant(): void
    {
        $restaurantA = Restaurant::create([
            'name' => 'Restaurant A',
            'slug' => 'restaurant-a',
        ]);

        $restaurantB = Restaurant::create([
            'name' => 'Restaurant B',
            'slug' => 'restaurant-b',
        ]);

        Branch::withoutGlobalScopes()->create([
            'restaurant_id' => $restaurantA->id,
            'name' => 'A Main',
            'slug' => 'main',
        ]);

        Branch::withoutGlobalScopes()->create([
            'restaurant_id' => $restaurantB->id,
            'name' => 'B Main',
            'slug' => 'main',
        ]);

        app(TenantContext::class)->set($restaurantA);

        $branches = Branch::query()->get();

        $this->assertCount(1, $branches);
        $this->assertSame('A Main', $branches->first()->name);
        $this->assertSame($restaurantA->id, $branches->first()->restaurant_id);
    }

    public function test_queries_fail_closed_without_a_tenant(): void
    {
        $restaurant = Restaurant::create([
            'name' => 'Restaurant A',
            'slug' => 'restaurant-a',
        ]);

        Branch::withoutGlobalScopes()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'A Main',
            'slug' => 'main',
        ]);

        app(TenantContext::class)->clear();

        $this->assertCount(0, Branch::query()->get());
    }

    public function test_branch_creation_automatically_uses_the_active_tenant(): void
    {
        $restaurant = Restaurant::create([
            'name' => 'Restaurant A',
            'slug' => 'restaurant-a',
        ]);

        app(TenantContext::class)->set($restaurant);

        $branch = Branch::create([
            'name' => 'A Main',
            'slug' => 'main',
        ]);

        $this->assertSame($restaurant->id, $branch->restaurant_id);
    }

    public function test_owner_registration_creates_tenant_and_main_branch(): void
    {
        $response = $this->post('/register', [
            'name' => 'Owner',
            'restaurant_name' => 'Nexora Demo',
            'email' => 'owner@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect('/dashboard');

        $user = User::where('email', 'owner@example.com')->firstOrFail();
        $restaurant = $user->restaurants()->firstOrFail();

        $this->assertSame('owner', $restaurant->pivot->role);
        $this->assertDatabaseHas('branches', [
            'restaurant_id' => $restaurant->id,
            'slug' => 'main',
        ]);
    }
}
