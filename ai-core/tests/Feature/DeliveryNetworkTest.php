<?php

namespace Tests\Feature;

use App\Models\DeliveryRider;
use App\Models\Menu;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\Template;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryNetworkTest extends TestCase
{
    use RefreshDatabase;

    public function test_rider_registration_is_public_and_linked_from_login_and_restaurant_registration(): void
    {
        $this->get(route('delivery.register'))->assertOk()->assertInertia(fn ($page) => $page->component('Delivery/Register'));
        $this->get(route('login'))->assertOk()->assertInertia(fn ($page) => $page->component('Auth/Login'));
        $this->get(route('register'))->assertOk()->assertInertia(fn ($page) => $page->component('Auth/Register'));
    }

    public function test_rider_can_register_and_receive_a_separate_delivery_dashboard(): void
    {
        $this->post(route('delivery.register.store'), [
            'name' => 'Rider One', 'email' => 'rider@example.com', 'phone' => '01012345678',
            'password' => 'StrongPassword123!', 'password_confirmation' => 'StrongPassword123!',
            'vehicle_type' => 'motorcycle', 'coverage_areas' => 'الزرقا، دمياط',
        ])->assertRedirect(route('delivery.dashboard'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('delivery_riders', ['phone' => '01012345678', 'status' => 'active']);
        $this->get(route('delivery.dashboard'))->assertOk()->assertInertia(fn ($page) => $page->component('Delivery/Dashboard'));
        $this->assertDatabaseHas('delivery_riders', ['phone' => '01012345678', 'open_to_area_orders' => false]);
        $this->patch(route('delivery.marketplace-availability'), ['open_to_area_orders' => true])->assertRedirect();
        $this->assertDatabaseHas('delivery_riders', ['phone' => '01012345678', 'open_to_area_orders' => true]);
    }

    public function test_rider_sees_only_ready_orders_in_coverage_area_and_can_claim_once(): void
    {
        $restaurant = Restaurant::create(['name' => 'Marketplace Restaurant', 'slug' => 'marketplace-restaurant', 'is_active' => true, 'delivery_enabled' => true]);
        $template = Template::create(['key' => 'marketplace-test', 'name' => 'Marketplace Test', 'is_active' => true]);
        $version = $template->createVersion(['layout' => []], true);
        $menu = Menu::create(['restaurant_id' => $restaurant->id, 'template_id' => $template->id, 'template_version_id' => $version->id, 'name' => 'Menu', 'slug' => 'marketplace-menu', 'is_published' => true]);

        $riderUser = User::factory()->create();
        $riderUser->deliveryRider()->create([
            'phone' => '01070000000', 'vehicle_type' => 'motorcycle', 'coverage_areas' => ['الزرقا'],
            'open_to_area_orders' => true, 'status' => 'active',
        ]);
        $available = Order::create([
            'restaurant_id' => $restaurant->id, 'menu_id' => $menu->id, 'order_number' => 'NX-MARKET-001',
            'customer_name' => 'Private Customer', 'customer_phone' => '01012345678', 'fulfillment_type' => 'delivery',
            'delivery_address' => 'شارع سري', 'delivery_area' => 'الزرقا', 'delivery_status' => 'unassigned',
            'status' => 'ready', 'items_count' => 2, 'subtotal' => 150, 'delivery_fee' => 25, 'total' => 175,
        ]);
        Order::create([
            'restaurant_id' => $restaurant->id, 'menu_id' => $menu->id, 'order_number' => 'NX-MARKET-002',
            'customer_name' => 'Other Area', 'customer_phone' => '01000000000', 'fulfillment_type' => 'delivery',
            'delivery_address' => 'دمياط', 'delivery_area' => 'دمياط', 'delivery_status' => 'unassigned',
            'status' => 'ready', 'items_count' => 1, 'subtotal' => 80, 'delivery_fee' => 15, 'total' => 95,
        ]);

        $this->actingAs($riderUser)->get(route('delivery.dashboard'))->assertOk()->assertInertia(fn ($page) => $page
            ->component('Delivery/Dashboard')->has('availableOrders', 1)
            ->where('availableOrders.0.id', $available->id)
            ->missing('availableOrders.0.customer_phone'));

        $this->actingAs($riderUser)->post(route('delivery.available-orders.claim', $available->id))->assertRedirect(route('delivery.dashboard'));
        $this->assertDatabaseHas('orders', ['id' => $available->id, 'delivery_rider_id' => $riderUser->deliveryRider->id, 'delivery_status' => 'assigned']);
        $this->assertDatabaseHas('delivery_order_events', ['order_id' => $available->id, 'event_type' => 'claimed_by_rider']);
        $this->actingAs($riderUser)->get(route('delivery.dashboard'))->assertInertia(fn ($page) => $page
            ->has('orders', 1)->where('orders.0.id', $available->id)->has('availableOrders', 0));
        $this->actingAs($riderUser)->post(route('delivery.available-orders.claim', $available->id))
            ->assertRedirect()->assertSessionHas('error', 'الطلب ده مندوب تاني استلمه بالفعل. حدّث القائمة واختار طلبًا متاحًا آخر.');
    }

    public function test_restaurant_can_invite_rider_and_assign_delivery_order_with_rider_updates(): void
    {
        $restaurant = Restaurant::create(['name' => 'Delivery Restaurant', 'slug' => 'delivery-restaurant', 'is_active' => true]);
        app(TenantContext::class)->set($restaurant);
        $owner = User::factory()->create();
        $owner->restaurants()->attach($restaurant->id, ['role' => 'owner']);
        $riderUser = User::factory()->create();
        $rider = DeliveryRider::create(['user_id' => $riderUser->id, 'phone' => '01000000000', 'vehicle_type' => 'motorcycle', 'coverage_areas' => ['الزرقا'], 'status' => 'active']);
        $template = Template::create(['key' => 'delivery-test', 'name' => 'Delivery Test', 'is_active' => true]);
        $version = $template->createVersion(['layout' => []], true);
        $menu = Menu::create(['restaurant_id' => $restaurant->id, 'template_id' => $template->id, 'template_version_id' => $version->id, 'name' => 'Menu', 'slug' => 'menu', 'is_published' => true]);
        $order = Order::create(['restaurant_id' => $restaurant->id, 'menu_id' => $menu->id, 'order_number' => 'NX-DELIVERY-001', 'customer_name' => 'Customer', 'customer_phone' => '01011111111', 'fulfillment_type' => 'delivery', 'delivery_address' => 'الزرقا - شارع البحر', 'status' => 'ready', 'items_count' => 1, 'subtotal' => 100, 'delivery_fee' => 20, 'total' => 120]);

        $this->actingAs($owner)->post(route('restaurant.delivery.invite'), ['delivery_rider_id' => $rider->id])->assertRedirect();
        $this->actingAs($riderUser)->post(route('delivery.invitations.respond', $restaurant->id), ['decision' => 'accept'])->assertRedirect();
        $this->assertDatabaseHas('restaurant_delivery_riders', ['restaurant_id' => $restaurant->id, 'delivery_rider_id' => $rider->id, 'status' => 'active']);

        $this->actingAs($owner)->patch(route('orders.delivery.assign', $order->id), ['delivery_rider_id' => $rider->id])->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'delivery_rider_id' => $rider->id, 'delivery_status' => 'assigned']);
        $order->update(['delivery_assigned_at' => now()->subMinutes(20)]);
        $this->actingAs($owner)->get(route('restaurant.delivery.index'))->assertOk()->assertInertia(fn ($page) => $page
            ->component('Delivery/Manage')->has('delayedOrders', 1)->where('delayedOrders.0.threshold_minutes', 15));

        $this->actingAs($riderUser)->post(route('delivery.orders.status', $order->id), ['_method' => 'PATCH', 'delivery_status' => 'picked_up'])->assertRedirect();
        $this->actingAs($riderUser)->post(route('delivery.orders.status', $order->id), ['_method' => 'PATCH', 'delivery_status' => 'delivered'])->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'delivery_status' => 'delivered']);
        $this->assertNotNull($order->fresh()->delivered_at);
        $this->assertDatabaseCount('delivery_order_events', 3);
        $this->assertDatabaseHas('delivery_order_events', ['order_id' => $order->id, 'event_type' => 'assigned', 'to_status' => 'assigned']);
        $this->assertDatabaseHas('delivery_order_events', ['order_id' => $order->id, 'event_type' => 'picked_up', 'from_status' => 'assigned', 'to_status' => 'picked_up']);
        $this->assertDatabaseHas('delivery_order_events', ['order_id' => $order->id, 'event_type' => 'delivered', 'from_status' => 'picked_up', 'to_status' => 'delivered']);
        $this->actingAs($owner)->get(route('restaurant.delivery.index'))->assertOk()->assertInertia(fn ($page) => $page
            ->component('Delivery/Manage')->has('orders.0.delivery_events', 3));
    }

    public function test_rider_cannot_update_an_order_assigned_to_someone_else(): void
    {
        $restaurant = Restaurant::create(['name' => 'Protected Restaurant', 'slug' => 'protected-restaurant', 'is_active' => true]);
        app(TenantContext::class)->set($restaurant);
        $template = Template::create(['key' => 'protected-delivery-test', 'name' => 'Protected Delivery Test', 'is_active' => true]);
        $version = $template->createVersion(['layout' => []], true);
        $menu = Menu::create(['restaurant_id' => $restaurant->id, 'template_id' => $template->id, 'template_version_id' => $version->id, 'name' => 'Menu', 'slug' => 'menu', 'is_published' => true]);
        $otherRiderUser = User::factory()->create();
        $otherRider = DeliveryRider::create(['user_id' => $otherRiderUser->id, 'phone' => '01000000000', 'vehicle_type' => 'bicycle', 'coverage_areas' => ['دمياط'], 'status' => 'active']);
        $order = Order::create(['restaurant_id' => $restaurant->id, 'menu_id' => $menu->id, 'order_number' => 'NX-DELIVERY-002', 'customer_name' => 'Customer', 'customer_phone' => '01011111111', 'fulfillment_type' => 'delivery', 'delivery_address' => 'دمياط', 'delivery_rider_id' => $otherRider->id, 'delivery_status' => 'assigned', 'status' => 'ready', 'items_count' => 1, 'subtotal' => 100, 'delivery_fee' => 20, 'total' => 120]);
        $stranger = User::factory()->create();
        $stranger->deliveryRider()->create(['phone' => '01099999999', 'vehicle_type' => 'walking', 'coverage_areas' => ['دمياط'], 'status' => 'active']);

        $this->actingAs($stranger)->post(route('delivery.orders.status', $order->id), ['_method' => 'PATCH', 'delivery_status' => 'picked_up'])->assertNotFound();
        $this->assertSame('assigned', $order->fresh()->delivery_status);
    }

    public function test_restaurant_can_batch_orders_for_the_same_rider_and_trip_completes_after_all_stops(): void
    {
        $restaurant = Restaurant::create(['name' => 'Trip Restaurant', 'slug' => 'trip-restaurant', 'is_active' => true]);
        app(TenantContext::class)->set($restaurant);
        $owner = User::factory()->create();
        $owner->restaurants()->attach($restaurant->id, ['role' => 'owner']);
        $riderUser = User::factory()->create();
        $rider = DeliveryRider::create(['user_id' => $riderUser->id, 'phone' => '01000000001', 'vehicle_type' => 'motorcycle', 'coverage_areas' => ['الزرقا'], 'status' => 'active']);
        $restaurant->deliveryRiders()->attach($rider->id, ['status' => 'active', 'invited_by' => $owner->id]);
        $template = Template::create(['key' => 'trip-test', 'name' => 'Trip Test', 'is_active' => true]);
        $version = $template->createVersion(['layout' => []], true);
        $menu = Menu::create(['restaurant_id' => $restaurant->id, 'template_id' => $template->id, 'template_version_id' => $version->id, 'name' => 'Menu', 'slug' => 'trip-menu', 'is_published' => true]);
        $orders = collect([1, 2])->map(fn ($number) => Order::create([
            'restaurant_id' => $restaurant->id, 'menu_id' => $menu->id, 'order_number' => 'NX-TRIP-00' . $number,
            'customer_name' => 'Customer ' . $number, 'customer_phone' => '01011111111', 'fulfillment_type' => 'delivery',
            'delivery_address' => 'الزرقا - شارع ' . $number, 'delivery_area' => 'الزرقا', 'delivery_rider_id' => $rider->id,
            'delivery_status' => 'assigned', 'status' => 'ready', 'items_count' => 1, 'subtotal' => 100, 'delivery_fee' => 20, 'total' => 120,
        ]));

        $this->actingAs($owner)->post(route('delivery.trips.store'), ['delivery_rider_id' => $rider->id, 'order_ids' => $orders->pluck('id')->all()])->assertRedirect();
        $this->assertDatabaseCount('delivery_trips', 1);
        $this->assertDatabaseCount('delivery_trip_orders', 2);
        $this->actingAs($owner)->get(route('delivery.trips.index'))->assertOk()->assertInertia(fn ($page) => $page->component('Delivery/Trips')->has('trips.0.orders', 2));
        $this->actingAs($riderUser)->get(route('delivery.dashboard'))->assertOk()->assertInertia(fn ($page) => $page->component('Delivery/Dashboard')->has('trips', 1)->has('trips.0.orders', 2));
        $this->actingAs($riderUser)->patch(route('delivery.orders.status', $orders[0]->id), ['delivery_status' => 'picked_up'])->assertStatus(422);
        $this->actingAs($riderUser)->patch(route('delivery.trips.rider-start', 1))->assertRedirect();
        $this->assertDatabaseHas('delivery_trips', ['id' => 1, 'status' => 'in_progress']);

        $this->actingAs($riderUser)->patch(route('delivery.orders.status', $orders[0]->id), ['delivery_status' => 'picked_up'])->assertRedirect();
        $this->actingAs($riderUser)->patch(route('delivery.orders.status', $orders[0]->id), ['delivery_status' => 'delivered'])->assertRedirect();
        $this->assertDatabaseHas('delivery_trips', ['id' => 1, 'status' => 'in_progress']);
        $this->actingAs($riderUser)->patch(route('delivery.orders.status', $orders[1]->id), ['delivery_status' => 'picked_up'])->assertRedirect();
        $this->actingAs($riderUser)->patch(route('delivery.orders.status', $orders[1]->id), ['delivery_status' => 'delivered'])->assertRedirect();
        $this->assertDatabaseHas('delivery_trips', ['id' => 1, 'status' => 'completed']);
    }

    public function test_authenticated_rider_can_log_out_and_cannot_reopen_the_rider_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->get(route('delivery.dashboard'))->assertRedirect(route('login'));
    }
    public function test_trip_rejects_orders_assigned_to_different_riders(): void
    {
        $restaurant = Restaurant::create(['name' => 'Mixed Trip Restaurant', 'slug' => 'mixed-trip-restaurant', 'is_active' => true]);
        app(TenantContext::class)->set($restaurant);
        $owner = User::factory()->create(); $owner->restaurants()->attach($restaurant->id, ['role' => 'owner']);
        $riderUser = User::factory()->create();
        $rider = DeliveryRider::create(['user_id' => $riderUser->id, 'phone' => '01000000002', 'vehicle_type' => 'motorcycle', 'coverage_areas' => ['الزرقا'], 'status' => 'active']);
        $otherUser = User::factory()->create();
        $otherRider = DeliveryRider::create(['user_id' => $otherUser->id, 'phone' => '01000000003', 'vehicle_type' => 'bicycle', 'coverage_areas' => ['الزرقا'], 'status' => 'active']);
        $restaurant->deliveryRiders()->attach([$rider->id => ['status' => 'active'], $otherRider->id => ['status' => 'active']]);
        $template = Template::create(['key' => 'mixed-trip-test', 'name' => 'Mixed Trip Test', 'is_active' => true]);
        $version = $template->createVersion(['layout' => []], true);
        $menu = Menu::create(['restaurant_id' => $restaurant->id, 'template_id' => $template->id, 'template_version_id' => $version->id, 'name' => 'Menu', 'slug' => 'mixed-trip-menu', 'is_published' => true]);
        $orders = collect([$rider, $otherRider])->map(fn ($assignedRider, $i) => Order::create([
            'restaurant_id' => $restaurant->id, 'menu_id' => $menu->id, 'order_number' => 'NX-MIXED-' . $i,
            'customer_name' => 'Customer', 'customer_phone' => '01011111111', 'fulfillment_type' => 'delivery', 'delivery_address' => 'الزرقا',
            'delivery_rider_id' => $assignedRider->id, 'delivery_status' => 'assigned', 'status' => 'ready', 'items_count' => 1, 'subtotal' => 100, 'total' => 100,
        ]));
        $this->actingAs($owner)->post(route('delivery.trips.store'), ['delivery_rider_id' => $rider->id, 'order_ids' => $orders->pluck('id')->all()])->assertStatus(422);
        $this->assertDatabaseCount('delivery_trips', 0);
    }
}
