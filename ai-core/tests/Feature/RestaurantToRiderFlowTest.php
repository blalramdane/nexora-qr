<?php

namespace Tests\Feature;

use App\Models\DeliveryRider;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\Order;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\Template;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestaurantToRiderFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_qr_delivery_order_flows_through_restaurant_rider_tracking_and_cash_settlement(): void
    {
        $restaurant = Restaurant::create([
            'name' => 'End-to-End Restaurant',
            'slug' => 'e2e-restaurant',
            'is_active' => true,
            'delivery_enabled' => true,
            'delivery_fee' => 25,
            'delivery_areas' => ['Zarqa'],
        ]);
        app(TenantContext::class)->set($restaurant);

        $owner = User::factory()->create();
        $owner->restaurants()->attach($restaurant->id, ['role' => 'owner']);

        $template = Template::create(['key' => 'e2e-fast-food', 'name' => 'E2E Fast Food', 'is_active' => true]);
        $version = $template->createVersion(['layout' => []], true);
        $menu = Menu::create([
            'restaurant_id' => $restaurant->id,
            'template_id' => $template->id,
            'template_version_id' => $version->id,
            'name' => 'Published Menu',
            'slug' => 'published-menu',
            'is_published' => true,
        ]);
        $category = MenuCategory::create([
            'restaurant_id' => $restaurant->id,
            'menu_id' => $menu->id,
            'name' => 'Main Menu',
            'slug' => 'main-menu',
            'is_active' => true,
        ]);
        $product = Product::create([
            'restaurant_id' => $restaurant->id,
            'menu_category_id' => $category->id,
            'name' => 'Test Meal',
            'slug' => 'test-meal',
            'price' => 100,
            'is_available' => true,
        ]);

        $riderUser = User::factory()->create();
        $rider = DeliveryRider::create([
            'user_id' => $riderUser->id,
            'phone' => '01000000000',
            'vehicle_type' => 'motorcycle',
            'coverage_areas' => ['Zarqa'],
            'open_to_area_orders' => true,
            'status' => 'active',
        ]);
        $restaurant->deliveryRiders()->attach($rider->id, ['status' => 'active', 'invited_by' => $owner->id]);

        $created = $this->postJson(route('public.orders.store', [$restaurant->slug, $menu->slug]), [
            'customer_name' => 'E2E Customer',
            'customer_phone' => '01011111111',
            'fulfillment_type' => 'delivery',
            'delivery_address' => 'Test Street 10',
            'delivery_area' => 'Zarqa',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated()
            ->assertJsonPath('order.status', 'new')
            ->assertJsonPath('order.total', '125.00');

        $order = Order::query()->where('order_number', $created->json('order.number'))->firstOrFail();
        $this->assertSame('unassigned', $order->delivery_status);
        $this->assertSame('unpaid', $order->payment_status);

        $this->actingAs($owner)->patch(route('orders.status', $order), ['status' => 'confirmed'])->assertRedirect(route('orders.index'));
        $this->actingAs($owner)->patch(route('orders.status', $order), ['status' => 'preparing'])->assertRedirect(route('orders.index'));
        $this->actingAs($owner)->patch(route('orders.status', $order), ['status' => 'ready'])->assertRedirect(route('orders.index'));

        $this->actingAs($riderUser)->get(route('delivery.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Delivery/Dashboard')->has('availableOrders', 1));
        $this->actingAs($riderUser)->post(route('delivery.available-orders.claim', $order->id))->assertRedirect(route('delivery.dashboard'));
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'delivery_rider_id' => $rider->id, 'delivery_status' => 'assigned']);

        $this->actingAs($riderUser)->patch(route('delivery.orders.status', $order->id), ['delivery_status' => 'picked_up'])->assertRedirect();
        $this->actingAs($riderUser)->patch(route('delivery.orders.status', $order->id), ['delivery_status' => 'delivered'])->assertRedirect();
        $this->actingAs($owner)->patch(route('orders.status', $order), ['status' => 'completed'])->assertRedirect(route('orders.index'));

        $trackingPath = parse_url($created->json('order.tracking_url'), PHP_URL_PATH);
        $this->get($trackingPath)->assertOk()->assertInertia(fn ($page) => $page
            ->component('Orders/Track')
            ->where('order.status', 'completed')
            ->where('order.deliveryStatus', 'delivered')
            ->where('order.deliveryRiderName', $riderUser->name));

        $this->actingAs($owner)->post(route('delivery.settlements.store'), [
            'delivery_rider_id' => $rider->id,
            'order_ids' => [$order->id],
            'collected_amount' => 125,
            'handed_over_amount' => 125,
        ])->assertRedirect(route('delivery.settlements.index'));

        $this->assertDatabaseHas('delivery_cash_settlements', [
            'restaurant_id' => $restaurant->id,
            'delivery_rider_id' => $rider->id,
            'expected_amount' => 125,
            'collected_amount' => 125,
            'handed_over_amount' => 125,
            'status' => 'confirmed',
        ]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'paid']);
        $this->assertDatabaseHas('delivery_rider_ledger_entries', ['delivery_rider_id' => $rider->id, 'type' => 'collection', 'amount' => 125]);
        $this->assertDatabaseHas('delivery_rider_ledger_entries', ['delivery_rider_id' => $rider->id, 'type' => 'handover', 'amount' => 125]);
        $this->assertDatabaseCount('orders', 1);
    }
}