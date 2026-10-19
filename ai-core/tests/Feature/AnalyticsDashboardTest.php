<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Restaurant;
use App\Models\Template;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_analytics_returns_period_trends_top_items_and_completed_revenue_for_the_active_restaurant(): void
    {
        [$restaurantA, $ownerA, $menuA] = $this->ownedMenu('Analytics A', 'analytics-a');
        [$restaurantB, , $menuB] = $this->ownedMenu('Analytics B', 'analytics-b');

        $completed = $this->order($restaurantA, $menuA, 'A-ORDER-1', 'completed', now()->subDay(), 100);
        OrderItem::create(['order_id' => $completed->id, 'product_name' => 'Classic Burger', 'quantity' => 2, 'unit_price' => 50, 'line_total' => 100]);
        $this->order($restaurantA, $menuA, 'A-ORDER-2', 'cancelled', now(), 40);
        $this->order($restaurantB, $menuB, 'B-ORDER-1', 'completed', now(), 900);
        app(TenantContext::class)->clear();

        $this->actingAs($ownerA)
            ->getJson('/owner/restaurants/'.$restaurantA->id.'/analytics?days=7')
            ->assertOk()
            ->assertJsonPath('range.days', 7)
            ->assertJsonPath('orders', 2)
            ->assertJsonPath('completed_orders', 1)
            ->assertJsonPath('revenue', 100)
            ->assertJsonPath('aov', 100)
            ->assertJsonPath('completion_rate', 50)
            ->assertJsonCount(7, 'trend')
            ->assertJsonPath('top_items.0.name', 'Classic Burger')
            ->assertJsonPath('top_items.0.quantity', 2)
            ->assertJsonPath('top_items.0.sales', 100)
            ->assertJsonPath('status_breakdown.0.count', 1);

        app(TenantContext::class)->clear();
        $this->actingAs($ownerA)->getJson('/analytics?days=30')
            ->assertOk()->assertJsonPath('range.days', 30)->assertJsonPath('orders', 2);

        app(TenantContext::class)->clear();
        $this->actingAs($ownerA)->getJson('/owner/restaurants/'.$restaurantB->id.'/analytics?days=7')->assertForbidden();

        app(TenantContext::class)->clear();
        $this->actingAs($ownerA)->getJson('/analytics?days=14')->assertUnprocessable();
    }

    private function ownedMenu(string $name, string $slug): array
    {
        $restaurant = Restaurant::create(['name' => $name, 'slug' => $slug, 'is_active' => true]);
        $owner = User::create(['name' => 'Owner '.$slug, 'email' => $slug.'@example.test', 'password' => 'test-password']);
        $owner->restaurants()->attach($restaurant->id, ['role' => 'owner']);
        app(TenantContext::class)->set($restaurant);

        $template = Template::create(['key' => 'template-'.$slug, 'name' => 'Fast Food '.$slug, 'is_active' => true]);
        $version = $template->createVersion(['layout' => 'fast-food'], true);
        $menu = Menu::create([
            'restaurant_id' => $restaurant->id,
            'template_id' => $template->id,
            'template_version_id' => $version->id,
            'name' => 'Menu '.$slug,
            'slug' => 'menu-'.$slug,
        ]);

        return [$restaurant, $owner, $menu];
    }

    private function order(Restaurant $restaurant, Menu $menu, string $number, string $status, $createdAt, float $total): Order
    {
        return Order::create([
            'restaurant_id' => $restaurant->id,
            'menu_id' => $menu->id,
            'order_number' => $number,
            'customer_name' => 'Analytics Test',
            'customer_phone' => '01000000000',
            'status' => $status,
            'items_count' => 1,
            'subtotal' => $total,
            'total' => $total,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
