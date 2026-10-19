<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\Template;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderStatusWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_status_must_follow_the_operational_workflow(): void
    {
        $restaurant = Restaurant::create(['name' => 'Workflow Restaurant', 'slug' => 'workflow-restaurant', 'is_active' => true]);
        app(TenantContext::class)->set($restaurant);
        $user = User::factory()->create();
        $user->restaurants()->attach($restaurant->id, ['role' => 'owner']);
        $template = Template::create(['key' => 'workflow-template', 'name' => 'Workflow', 'is_active' => true]);
        $version = $template->createVersion(['layout' => []], true);
        $menu = Menu::create(['restaurant_id' => $restaurant->id, 'template_id' => $template->id, 'template_version_id' => $version->id, 'name' => 'Menu', 'slug' => 'menu', 'is_published' => true]);
        $order = Order::create(['restaurant_id' => $restaurant->id, 'menu_id' => $menu->id, 'order_number' => 'NX-TEST-001', 'customer_name' => 'Test', 'customer_phone' => '01000000000', 'status' => 'new', 'items_count' => 1, 'subtotal' => 50, 'total' => 50]);

        $this->actingAs($user)->patch(route('orders.status', $order), ['status' => 'preparing'])->assertUnprocessable();
        $this->assertSame('new', $order->fresh()->status);

        $this->actingAs($user)->patch(route('orders.status', $order), ['status' => 'confirmed'])->assertRedirect(route('orders.index'));
        $this->assertSame('confirmed', $order->fresh()->status);

        $this->actingAs($user)->patch(route('orders.status', $order), ['status' => 'preparing'])->assertRedirect(route('orders.index'));
        $this->actingAs($user)->patch(route('orders.status', $order), ['status' => 'ready'])->assertRedirect(route('orders.index'));
        $this->actingAs($user)->patch(route('orders.status', $order), ['status' => 'completed'])->assertRedirect(route('orders.index'));
        $this->actingAs($user)->patch(route('orders.status', $order), ['status' => 'preparing'])->assertUnprocessable();
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertDatabaseCount('order_status_histories', 4);
        $this->assertDatabaseHas('order_status_histories', ['order_id' => $order->id, 'from_status' => 'new', 'to_status' => 'confirmed', 'changed_by' => $user->id]);
    }

    public function test_payment_status_can_be_marked_paid_then_refunded_but_cannot_skip_steps(): void
    {
        $restaurant = Restaurant::create(['name' => 'Payment Restaurant', 'slug' => 'payment-restaurant', 'is_active' => true]);
        app(TenantContext::class)->set($restaurant);
        $user = User::factory()->create();
        $user->restaurants()->attach($restaurant->id, ['role' => 'owner']);
        $template = Template::create(['key' => 'payment-template', 'name' => 'Payment', 'is_active' => true]);
        $version = $template->createVersion(['layout' => []], true);
        $menu = Menu::create(['restaurant_id' => $restaurant->id, 'template_id' => $template->id, 'template_version_id' => $version->id, 'name' => 'Menu', 'slug' => 'payment-menu', 'is_published' => true]);
        $order = Order::create(['restaurant_id' => $restaurant->id, 'menu_id' => $menu->id, 'order_number' => 'NX-PAY-001', 'customer_name' => 'Test', 'customer_phone' => '01000000000', 'status' => 'completed', 'items_count' => 1, 'subtotal' => 50, 'total' => 50, 'payment_status' => 'unpaid']);

        $this->actingAs($user)->patch(route('orders.payment.update', $order), ['payment_status' => 'refunded'])->assertUnprocessable();
        $this->assertSame('unpaid', $order->fresh()->payment_status);

        $this->actingAs($user)->patch(route('orders.payment.update', $order), ['payment_status' => 'paid'])->assertRedirect(route('orders.index'));
        $this->assertSame('paid', $order->fresh()->payment_status);

        $this->actingAs($user)->patch(route('orders.payment.update', $order), ['payment_status' => 'refunded'])->assertRedirect(route('orders.index'));
        $this->assertSame('refunded', $order->fresh()->payment_status);
        $this->actingAs($user)->patch(route('orders.payment.update', $order), ['payment_status' => 'paid'])->assertUnprocessable();
    }

    public function test_kitchen_display_returns_orders_in_active_workflow_states(): void
    {
        $restaurant = Restaurant::create(['name' => 'Kitchen Restaurant', 'slug' => 'kitchen-restaurant', 'is_active' => true]);
        app(TenantContext::class)->set($restaurant);
        $user = User::factory()->create();
        $user->restaurants()->attach($restaurant->id, ['role' => 'owner']);
        $template = Template::create(['key' => 'kitchen-template', 'name' => 'Kitchen', 'is_active' => true]);
        $version = $template->createVersion(['layout' => []], true);
        $menu = Menu::create(['restaurant_id' => $restaurant->id, 'template_id' => $template->id, 'template_version_id' => $version->id, 'name' => 'Menu', 'slug' => 'kitchen-menu', 'is_published' => true]);

        $newOrder = null;
        foreach (['new', 'confirmed', 'preparing', 'ready', 'completed'] as $index => $status) {
            $order = Order::create([
                'restaurant_id' => $restaurant->id,
                'menu_id' => $menu->id,
                'order_number' => 'NX-KDS-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'customer_name' => 'Kitchen Customer '.$index,
                'customer_phone' => '01000000000',
                'notes' => $status === 'new' ? 'بدون بصل' : null,
                'status' => $status,
                'items_count' => 1,
                'subtotal' => 50,
                'total' => 50,
            ]);
            if ($status === 'new') {
                $newOrder = $order;
            }
        }
        $this->assertNotNull($newOrder);
        $newOrder->items()->create([
            'product_name' => 'Classic Burger',
            'variant_name' => 'Large',
            'modifiers' => [['name' => 'Extra cheese', 'price' => 10]],
            'quantity' => 1,
            'unit_price' => 60,
            'line_total' => 60,
        ]);

        $this->actingAs($user)
            ->get(route('kds'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard/KDS')
                ->where('ordersBaseUrl', '/orders'));

        $this->get(route('orders.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Orders/Index')
                ->where('kdsUrl', '/kds'));

        $this->get(route('owner.restaurants.kds', $restaurant))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard/KDS')
                ->where('ordersBaseUrl', '/owner/restaurants/'.$restaurant->id.'/orders'));

        $this->get(route('owner.restaurants.orders.index', $restaurant))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Orders/Index')
                ->where('kdsUrl', '/owner/restaurants/'.$restaurant->id.'/kds'));

        $kdsResponse = $this->actingAs($user)
            ->getJson(route('orders.kds'));
        $kdsResponse
            ->assertOk()
            ->assertJsonCount(4)
            ->assertJsonFragment(['order_number' => 'NX-KDS-000', 'status' => 'new'])
            ->assertJsonFragment(['order_number' => 'NX-KDS-001', 'status' => 'confirmed'])
            ->assertJsonFragment(['order_number' => 'NX-KDS-002', 'status' => 'preparing'])
            ->assertJsonFragment(['order_number' => 'NX-KDS-003', 'status' => 'ready'])
            ->assertJsonMissing(['order_number' => 'NX-KDS-004', 'status' => 'completed']);

        $newOrderPayload = collect($kdsResponse->json())->firstWhere('order_number', 'NX-KDS-000');
        $this->assertNotNull($newOrderPayload);
        $this->assertSame('بدون بصل', $newOrderPayload['notes']);
        $this->assertSame('Classic Burger', $newOrderPayload['items'][0]['product_name']);
        $this->assertSame('Large', $newOrderPayload['items'][0]['variant_name']);
        $this->assertSame(['Extra cheese'], $newOrderPayload['items'][0]['modifiers']);

        $ownerKdsResponse = $this->getJson(route('owner.restaurants.orders.kds', $restaurant))
            ->assertOk()
            ->assertJsonFragment(['order_number' => 'NX-KDS-000', 'status' => 'new']);
        $ownerKdsOrder = collect($ownerKdsResponse->json())->firstWhere('order_number', 'NX-KDS-000');
        $this->assertSame('Classic Burger', $ownerKdsOrder['items'][0]['product_name']);
    }
}
