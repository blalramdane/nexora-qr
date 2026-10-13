<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\MenuCategory;
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
}
