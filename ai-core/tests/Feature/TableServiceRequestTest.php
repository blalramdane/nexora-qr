<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\RestaurantTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TableServiceRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_table_service_request_requires_the_active_table_qr_token(): void
    {
        $restaurant = Restaurant::create(['name' => 'Table Service Test', 'slug' => 'table-service-test']);
        $token = Str::random(48);
        $table = RestaurantTable::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Table 1',
            'qr_token_hash' => hash('sha256', $token),
            'is_active' => true,
        ]);

        $this->postJson('/service-requests', [
            'restaurant_table_id' => $table->id,
            'table_token' => $token,
            'request_type' => 'call_waiter',
        ])->assertCreated()
            ->assertJsonPath('restaurant_id', $restaurant->id)
            ->assertJsonPath('restaurant_table_id', $table->id)
            ->assertJsonPath('status', 'pending');

        $this->assertDatabaseHas('table_service_requests', [
            'restaurant_id' => $restaurant->id,
            'restaurant_table_id' => $table->id,
            'request_type' => 'call_waiter',
            'status' => 'pending',
        ]);

        $this->postJson('/service-requests', [
            'restaurant_table_id' => $table->id,
            'table_token' => Str::random(48),
            'request_type' => 'request_bill',
        ])->assertNotFound();

        $inactiveToken = Str::random(48);
        $inactive = RestaurantTable::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Table 2',
            'qr_token_hash' => hash('sha256', $inactiveToken),
            'is_active' => false,
        ]);
        $this->postJson('/service-requests', [
            'restaurant_table_id' => $inactive->id,
            'table_token' => $inactiveToken,
            'request_type' => 'call_waiter',
        ])->assertNotFound();

        $this->assertDatabaseCount('table_service_requests', 1);
    }

    public function test_public_table_service_request_validates_type_token_and_note(): void
    {
        $this->postJson('/service-requests', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['restaurant_table_id', 'table_token', 'request_type']);
    }
}
