<?php

namespace Tests\Feature;

use App\Jobs\SendRestaurantOrderWhatsAppNotification;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\Order;
use App\Models\OrderIntegrationEvent;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\Template;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OrderNotificationChannelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_restaurant_settings_expose_two_independent_order_notification_channels(): void
    {
        [$user, $restaurant] = $this->makeOwnedRestaurant('Channel Settings');
        app(TenantContext::class)->clear();

        $this->actingAs($user)->get('/settings/orders')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('RestaurantSettings')
                ->where('settings.dashboard_order_notifications_enabled', true)
                ->where('settings.whatsapp_order_notifications_enabled', false)
                ->where('whatsappCloudConfigured', false));

        app(TenantContext::class)->clear();
        $this->actingAs($user)->put('/settings/orders', $this->settingsPayload([
            'dashboard_order_notifications_enabled' => false,
            'whatsapp_order_notifications_enabled' => false,
        ]))->assertSessionHas('success');

        $this->assertDatabaseHas('restaurants', [
            'id' => $restaurant->id,
            'dashboard_order_notifications_enabled' => false,
            'whatsapp_order_notifications_enabled' => false,
        ]);

        app(TenantContext::class)->clear();
        $this->actingAs($user)->get('/orders')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Orders/Index')
                ->where('dashboardOrderNotificationsEnabled', false)
                ->where('settingsUrl', route('restaurant.settings.edit')));
    }

    public function test_whatsapp_auto_notifications_cannot_be_enabled_until_cloud_api_is_configured(): void
    {
        Config::set('services.whatsapp_cloud', [
            'access_token' => null,
            'phone_number_id' => null,
            'api_version' => null,
            'order_template_name' => null,
            'order_template_language' => 'ar',
        ]);
        [$user, $restaurant] = $this->makeOwnedRestaurant('Unconfigured WhatsApp');
        app(TenantContext::class)->clear();

        $this->actingAs($user)->put('/settings/orders', $this->settingsPayload([
            'dashboard_order_notifications_enabled' => true,
            'whatsapp_order_notifications_enabled' => true,
        ]))->assertSessionHasErrors('whatsapp_order_notifications_enabled');

        $this->assertDatabaseHas('restaurants', [
            'id' => $restaurant->id,
            'dashboard_order_notifications_enabled' => true,
            'whatsapp_order_notifications_enabled' => false,
        ]);
    }

    public function test_new_public_order_is_saved_and_queues_a_whatsapp_notification_when_enabled(): void
    {
        Queue::fake();
        [$user, $restaurant, $menu, $product] = $this->makeOwnedRestaurant('Queued WhatsApp');
        $restaurant->update([
            'whatsapp_phone' => '201028282726',
            'whatsapp_order_notifications_enabled' => true,
        ]);
        app(TenantContext::class)->clear();

        $response = $this->postJson(route('public.orders.store', [$restaurant->slug, $menu->slug]), [
            'customer_name' => 'QR Guest',
            'customer_phone' => '01000000000',
            'fulfillment_type' => 'pickup',
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])->assertCreated()->assertJsonPath('order.total', '80.00');

        $order = Order::query()->where('order_number', $response->json('order.number'))->firstOrFail();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'restaurant_id' => $restaurant->id, 'total' => 80]);
        Queue::assertPushed(SendRestaurantOrderWhatsAppNotification::class, fn ($job) => $job->orderId === $order->id);
        $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'product_id' => $product->id, 'quantity' => 2]);
    }

    public function test_whatsapp_job_sends_an_approved_template_and_records_the_provider_message_id(): void
    {
        Config::set('services.whatsapp_cloud', [
            'access_token' => 'test-access-token',
            'phone_number_id' => 'phone-number-123',
            'api_version' => 'v99.0',
            'order_template_name' => 'restaurant_new_order',
            'order_template_language' => 'ar',
        ]);
        Http::fake([
            'https://graph.facebook.com/v99.0/phone-number-123/messages' => Http::response([
                'messaging_product' => 'whatsapp',
                'messages' => [['id' => 'wamid.test-123']],
            ], 200),
        ]);

        [, $restaurant, $menu, $product] = $this->makeOwnedRestaurant('WhatsApp Delivery');
        $restaurant->update(['whatsapp_phone' => '+20 102 828 2726', 'whatsapp_order_notifications_enabled' => true]);
        $order = $this->makeOrder($restaurant, $menu, $product);

        (new SendRestaurantOrderWhatsAppNotification($order->id))->handle();

        Http::assertSent(function (Request $request): bool {
            $body = $request->data();
            return $request->url() === 'https://graph.facebook.com/v99.0/phone-number-123/messages'
                && $request->hasHeader('Authorization', 'Bearer test-access-token')
                && ($body['messaging_product'] ?? null) === 'whatsapp'
                && ($body['to'] ?? null) === '201028282726'
                && ($body['type'] ?? null) === 'template'
                && ($body['template']['name'] ?? null) === 'restaurant_new_order'
                && count($body['template']['components'][0]['parameters'] ?? []) === 4;
        });

        $this->assertDatabaseHas('order_integration_events', [
            'order_id' => $order->id,
            'event_type' => 'notification.whatsapp.order_created',
            'status' => 'sent',
        ]);
        $event = OrderIntegrationEvent::query()->where('order_id', $order->id)->where('event_type', 'notification.whatsapp.order_created')->firstOrFail();
        $this->assertSame('wamid.test-123', $event->payload['provider_message_id']);
    }

    public function test_whatsapp_job_does_not_call_meta_when_credentials_are_missing(): void
    {
        Config::set('services.whatsapp_cloud', [
            'access_token' => null,
            'phone_number_id' => null,
            'api_version' => null,
            'order_template_name' => null,
            'order_template_language' => 'ar',
        ]);
        Http::fake();
        [, $restaurant, $menu, $product] = $this->makeOwnedRestaurant('WhatsApp Not Ready');
        $restaurant->update(['whatsapp_phone' => '201028282726', 'whatsapp_order_notifications_enabled' => true]);
        $order = $this->makeOrder($restaurant, $menu, $product);

        (new SendRestaurantOrderWhatsAppNotification($order->id))->handle();

        Http::assertNothingSent();
        $this->assertDatabaseHas('order_integration_events', [
            'order_id' => $order->id,
            'event_type' => 'notification.whatsapp.order_created',
            'status' => 'failed',
        ]);
    }

    private function makeOwnedRestaurant(string $name): array
    {
        $user = User::factory()->create();
        $restaurant = Restaurant::create(['name' => $name, 'slug' => str($name)->slug()->toString(), 'is_active' => true]);
        $restaurant->users()->attach($user->id, ['role' => 'owner']);
        app(TenantContext::class)->set($restaurant);

        $template = Template::create(['key' => 'template-'.$restaurant->id, 'name' => 'Test Template', 'is_active' => true]);
        $version = $template->createVersion(['layout' => []], true);
        $menu = Menu::create([
            'restaurant_id' => $restaurant->id,
            'template_id' => $template->id,
            'template_version_id' => $version->id,
            'name' => 'Test Menu',
            'slug' => 'test-menu',
            'is_published' => true,
            'theme' => ['primary' => '#111827', 'accent' => '#D6A85F', 'background' => '#fff', 'foreground' => '#111827', 'radius' => 'xl'],
        ]);
        $category = MenuCategory::create([
            'restaurant_id' => $restaurant->id, 'menu_id' => $menu->id,
            'name' => 'Main', 'slug' => 'main', 'sort_order' => 0, 'is_active' => true,
        ]);
        $product = Product::create([
            'restaurant_id' => $restaurant->id, 'menu_category_id' => $category->id,
            'name' => 'Test Product', 'slug' => 'test-product', 'price' => 40,
            'is_available' => true, 'is_featured' => false, 'sort_order' => 0,
        ]);

        return [$user, $restaurant, $menu, $product];
    }

    private function makeOrder(Restaurant $restaurant, Menu $menu, Product $product): Order
    {
        $order = Order::create([
            'restaurant_id' => $restaurant->id,
            'menu_id' => $menu->id,
            'source' => 'qr_menu',
            'payment_method' => 'cash_on_delivery',
            'payment_status' => 'unpaid',
            'order_number' => 'NX-TEST-'.strtoupper(str()->random(6)),
            'customer_name' => 'Test Customer',
            'customer_phone' => '01000000000',
            'fulfillment_type' => 'pickup',
            'status' => 'new',
            'items_count' => 1,
            'subtotal' => 40,
            'delivery_fee' => 0,
            'discount_amount' => 0,
            'total' => 40,
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 40,
            'line_total' => 40,
        ]);

        return $order;
    }

    private function settingsPayload(array $overrides = []): array
    {
        return array_merge([
            'whatsapp_phone' => '201028282726',
            'phone' => '01000000000',
            'facebook_url' => null,
            'instagram_url' => null,
            'tiktok_url' => null,
            'website_url' => null,
            'delivery_fee' => 0,
            'delivery_areas' => '',
            'delivery_enabled' => false,
            'pickup_enabled' => true,
            'dashboard_order_notifications_enabled' => true,
            'whatsapp_order_notifications_enabled' => false,
            'pos_integration_enabled' => false,
            'pos_provider' => null,
            'pos_endpoint_url' => null,
            'pos_integration_notes' => null,
        ], $overrides);
    }
}
