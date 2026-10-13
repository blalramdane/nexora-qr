<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\Modifier;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Order;
use App\Models\Coupon;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\Template;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_menu_is_public_and_renders_its_pinned_version_even_when_inactive(): void
    {
        [$restaurant, $menu, $version] = $this->makeMenu();
        $newVersion = $menu->template->createVersion(['layout' => 'new'], true);

        $this->assertFalse($version->fresh()->is_active);
        $this->assertTrue($newVersion->fresh()->is_active);

        $this->get(route('public.menu.show', [$restaurant->slug, $menu->slug]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Menu/Public')
                ->where('restaurant.name', 'Public Restaurant')
                ->where('menu.slug', 'lunch')
                ->where('menu.template_version.version', 1)
                ->where('menu.categories.0.name', 'Burgers')
                ->where('menu.categories.0.products.0.name', 'Classic Burger'));
    }

    public function test_unpublished_menu_is_not_public(): void
    {
        [$restaurant, $menu] = $this->makeMenu(false);

        $this->get(route('public.menu.show', [$restaurant->slug, $menu->slug]))
            ->assertNotFound();
    }

    public function test_public_menu_exposes_restaurant_whatsapp_for_the_customer_order_handoff(): void
    {
        [$restaurant, $menu] = $this->makeMenu();
        $restaurant->update(['whatsapp_phone' => '+20 10 1234 5678']);

        $this->get(route('public.menu.show', [$restaurant->slug, $menu->slug]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Menu/Public')
                ->where('restaurant.whatsapp_phone', '+20 10 1234 5678'));
    }

    public function test_public_menu_route_cannot_leak_a_menu_from_another_restaurant(): void
    {
        [$restaurant] = $this->makeMenu();
        [$otherRestaurant, $otherMenu] = $this->makeMenu(true, 'Other Restaurant', 'other');

        $this->get(route('public.menu.show', [$restaurant->slug, $otherMenu->slug]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('restaurant.name', 'Public Restaurant')
                ->where('menu.name', 'Lunch Menu'));
    }

    public function test_customer_can_submit_an_order_and_server_calculates_the_total(): void
    {
        [$restaurant, $menu] = $this->makeMenu();
        $product = Product::query()->where('restaurant_id', $restaurant->id)->firstOrFail();

        $this->postJson(route('public.orders.store', [$restaurant->slug, $menu->slug]), [
            'customer_name' => 'Test Customer',
            'customer_phone' => '01000000000',
            'fulfillment_type' => 'pickup',
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])->assertCreated()
            ->assertJsonPath('order.total', '200.00')
            ->assertJsonPath('order.status', 'new');

        $this->assertDatabaseHas('orders', [
            'restaurant_id' => $restaurant->id,
            'menu_id' => $menu->id,
            'customer_name' => 'Test Customer',
            'total' => 200,
            'status' => 'new',
        ]);
        $this->assertDatabaseHas('order_items', ['product_id' => $product->id, 'quantity' => 2, 'line_total' => 200]);
    }

    public function test_customer_can_apply_a_coupon_and_discount_is_recalculated_and_snapshotted_on_order(): void
    {
        [$restaurant, $menu] = $this->makeMenu();
        $product = Product::query()->where('restaurant_id', $restaurant->id)->firstOrFail();
        $coupon = Coupon::create([
            'restaurant_id' => $restaurant->id, 'code' => 'SAVE10', 'type' => 'percent', 'value' => 10,
            'minimum_subtotal' => 50, 'maximum_discount' => 25, 'usage_limit' => 1, 'usage_count' => 0, 'is_active' => true,
        ]);

        $this->postJson(route('public.coupons.check', [$restaurant->slug, $menu->slug]), ['coupon_code' => 'save10', 'subtotal' => 100])
            ->assertOk()->assertJsonPath('code', 'SAVE10')->assertJsonPath('discount_amount', 10);

        $this->postJson(route('public.orders.store', [$restaurant->slug, $menu->slug]), [
            'customer_name' => 'Coupon Customer', 'customer_phone' => '01000000000', 'fulfillment_type' => 'pickup',
            'coupon_code' => 'save10', 'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated()->assertJsonPath('order.subtotal', '100.00')->assertJsonPath('order.discount_amount', '10.00')->assertJsonPath('order.total', '90.00');

        $order = Order::query()->where('customer_name', 'Coupon Customer')->firstOrFail();
        $this->assertSame($coupon->id, $order->coupon_id);
        $this->assertSame('SAVE10', $order->coupon_code);
        $this->assertSame(1, $coupon->fresh()->usage_count);

        $this->postJson(route('public.orders.store', [$restaurant->slug, $menu->slug]), [
            'customer_name' => 'Second Customer', 'customer_phone' => '01000000001', 'fulfillment_type' => 'pickup',
            'coupon_code' => 'SAVE10', 'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertUnprocessable();
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_fixed_coupon_cannot_discount_more_than_products_subtotal_and_minimum_is_enforced(): void
    {
        [$restaurant, $menu] = $this->makeMenu();
        $product = Product::query()->where('restaurant_id', $restaurant->id)->firstOrFail();
        Coupon::create(['restaurant_id' => $restaurant->id, 'code' => 'FIXED500', 'type' => 'fixed', 'value' => 500, 'minimum_subtotal' => 150, 'is_active' => true]);

        $this->postJson(route('public.coupons.check', [$restaurant->slug, $menu->slug]), ['coupon_code' => 'FIXED500', 'subtotal' => 100])
            ->assertUnprocessable();

        $this->postJson(route('public.orders.store', [$restaurant->slug, $menu->slug]), [
            'customer_name' => 'Fixed Customer', 'customer_phone' => '01000000002', 'fulfillment_type' => 'pickup',
            'coupon_code' => 'FIXED500', 'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])->assertCreated()->assertJsonPath('order.discount_amount', '200.00')->assertJsonPath('order.total', '0.00');
    }

    public function test_coupon_from_another_restaurant_cannot_be_applied(): void
    {
        [$restaurant, $menu] = $this->makeMenu();
        [$otherRestaurant] = $this->makeMenu(true, 'Other Restaurant', 'other-coupon-restaurant');
        Coupon::create(['restaurant_id' => $otherRestaurant->id, 'code' => 'OTHER10', 'type' => 'fixed', 'value' => 10, 'is_active' => true]);

        $this->postJson(route('public.coupons.check', [$restaurant->slug, $menu->slug]), ['coupon_code' => 'OTHER10', 'subtotal' => 100])
            ->assertUnprocessable();
    }

    public function test_customer_can_track_order_using_private_tracking_link(): void
    {
        [$restaurant, $menu] = $this->makeMenu();
        $product = Product::query()->where('restaurant_id', $restaurant->id)->firstOrFail();

        $response = $this->postJson(route('public.orders.store', [$restaurant->slug, $menu->slug]), [
            'customer_name' => 'Track Customer',
            'customer_phone' => '01000000000',
            'fulfillment_type' => 'pickup',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated()->assertJsonStructure(['order' => ['number', 'tracking_url']]);

        $trackingPath = parse_url($response->json('order.tracking_url'), PHP_URL_PATH);
        $this->get($trackingPath)->assertOk()->assertInertia(fn ($page) => $page
            ->component('Orders/Track')
            ->where('order.number', $response->json('order.number'))
            ->where('order.status', 'new')
            ->where('order.history.0.status', 'new')
            ->where('restaurant.name', $restaurant->name));
        $this->assertDatabaseHas('order_status_histories', ['to_status' => 'new', 'from_status' => null]);
    }

    public function test_order_tracking_rejects_invalid_private_token(): void
    {
        [$restaurant] = $this->makeMenu();

        $this->get(route('public.orders.track', [$restaurant->slug, 'NX-NOT-REAL', str_repeat('x', 64)]))
            ->assertNotFound();
    }

    public function test_customer_can_review_only_a_completed_order_once_using_its_private_tracking_token(): void
    {
        [$restaurant, $menu] = $this->makeMenu();
        $product = Product::query()->where('restaurant_id', $restaurant->id)->firstOrFail();
        $response = $this->postJson(route('public.orders.store', [$restaurant->slug, $menu->slug]), [
            'customer_name' => 'Review Customer', 'customer_phone' => '01000000000', 'fulfillment_type' => 'pickup',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated();

        $trackingUrl = $response->json('order.tracking_url');
        preg_match('#/r/([^/]+)/orders/([^/]+)/track/([^/]+)#', $trackingUrl, $matches);
        $this->assertCount(4, $matches);
        [, $slug, $number, $token] = $matches;
        $order = Order::query()->where('order_number', $number)->firstOrFail();

        $this->post(route('public.orders.review', [$slug, $number, $token]), ['rating' => 5, 'comment' => 'تجربة ممتازة'])
            ->assertSessionHasErrors('rating');
        $order->update(['status' => 'completed']);

        $this->post(route('public.orders.review', [$slug, $number, $token]), ['rating' => 5, 'comment' => 'تجربة ممتازة'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('order_reviews', ['restaurant_id' => $restaurant->id, 'order_id' => $order->id, 'rating' => 5, 'comment' => 'تجربة ممتازة']);

        $this->post(route('public.orders.review', [$slug, $number, $token]), ['rating' => 4])
            ->assertSessionHasErrors('rating');
        $this->get(route('public.orders.track', [$slug, $number, $token]))
            ->assertOk()->assertInertia(fn ($page) => $page->where('order.review.rating', 5)->where('order.review.comment', 'تجربة ممتازة'));
    }

    public function test_review_requires_a_valid_private_tracking_token_and_valid_rating(): void
    {
        [$restaurant, $menu] = $this->makeMenu();
        $product = Product::query()->where('restaurant_id', $restaurant->id)->firstOrFail();
        $response = $this->postJson(route('public.orders.store', [$restaurant->slug, $menu->slug]), [
            'customer_name' => 'Review Customer', 'customer_phone' => '01000000000', 'fulfillment_type' => 'pickup',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated();
        preg_match('#/r/([^/]+)/orders/([^/]+)/track/([^/]+)#', $response->json('order.tracking_url'), $matches);
        [, $slug, $number, $token] = $matches;
        Order::query()->where('order_number', $number)->update(['status' => 'completed']);

        $this->post(route('public.orders.review', [$slug, $number, str_repeat('x', 64)]), ['rating' => 5])->assertNotFound();
        $this->post(route('public.orders.review', [$slug, $number, $token]), ['rating' => 6])->assertSessionHasErrors('rating');
        $this->assertDatabaseCount('order_reviews', 0);
    }

    public function test_delivery_order_requires_address_and_adds_restaurant_delivery_fee(): void
    {
        [$restaurant, $menu] = $this->makeMenu();
        $restaurant->update(['delivery_fee' => 25, 'delivery_enabled' => true, 'delivery_areas' => ['الزرقا', 'دمياط']]);
        $product = Product::query()->where('restaurant_id', $restaurant->id)->firstOrFail();

        $this->postJson(route('public.orders.store', [$restaurant->slug, $menu->slug]), [
            'customer_name' => 'Delivery Customer',
            'customer_phone' => '01000000000',
            'fulfillment_type' => 'delivery',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertUnprocessable()->assertJsonValidationErrors('delivery_address');

        $this->postJson(route('public.orders.store', [$restaurant->slug, $menu->slug]), [
            'customer_name' => 'Delivery Customer', 'customer_phone' => '01000000000',
            'fulfillment_type' => 'delivery', 'delivery_address' => 'شارع البحر، دمياط',
            'delivery_area' => 'مدينة غير متاحة', 'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertUnprocessable()->assertJsonPath('message', 'اختار منطقة من مناطق التوصيل المتاحة للمطعم.');

        $this->postJson(route('public.orders.store', [$restaurant->slug, $menu->slug]), [
            'customer_name' => 'Delivery Customer',
            'customer_phone' => '01000000000',
            'fulfillment_type' => 'delivery',
            'delivery_address' => 'شارع البحر، دمياط',
            'delivery_area' => 'دمياط',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated()->assertJsonPath('order.total', '125.00')->assertJsonPath('order.delivery_fee', '25.00');

        $this->assertDatabaseHas('orders', ['customer_name' => 'Delivery Customer', 'fulfillment_type' => 'delivery', 'delivery_address' => 'شارع البحر، دمياط', 'delivery_area' => 'دمياط', 'delivery_fee' => 25, 'total' => 125]);
    }

    public function test_customer_cannot_order_a_product_from_another_menu(): void
    {
        [$restaurant, $menu] = $this->makeMenu();
        [$otherRestaurant] = $this->makeMenu(true, 'Other Restaurant', 'other');
        $otherProduct = Product::query()->withoutGlobalScopes()->where('restaurant_id', $otherRestaurant->id)->firstOrFail();

        $this->postJson(route('public.orders.store', [$restaurant->slug, $menu->slug]), [
            'customer_name' => 'Test Customer', 'customer_phone' => '01000000000',
            'items' => [['product_id' => $otherProduct->id, 'quantity' => 1]],
        ])->assertStatus(422);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_customer_must_select_a_variant_when_the_product_has_active_variants(): void
    {
        [$restaurant, $menu] = $this->makeMenu();
        $product = Product::query()->where('restaurant_id', $restaurant->id)->firstOrFail();
        $variant = ProductVariant::create([
            'restaurant_id' => $restaurant->id,
            'product_id' => $product->id,
            'name' => 'Large',
            'price' => 160,
            'price_delta' => 0,
            'is_active' => true,
        ]);
        $route = route('public.orders.store', [$restaurant->slug, $menu->slug]);
        $payload = [
            'customer_name' => 'Variant Customer',
            'customer_phone' => '01000000000',
            'fulfillment_type' => 'pickup',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ];

        $this->postJson($route, $payload)->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);

        $payload['items'][0]['variant_id'] = $variant->id;
        $this->postJson($route, $payload)->assertCreated()->assertJsonPath('order.total', '160.00');
        $this->assertDatabaseHas('order_items', ['product_id' => $product->id, 'unit_price' => 160, 'variant_name' => 'Large']);
    }

    public function test_table_qr_is_tenant_scoped_and_creates_a_dine_in_order_with_table_snapshot(): void
    {
        [$restaurant, $menu] = $this->makeMenu();
        $token = Str::random(64);
        $table = RestaurantTable::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Table 07',
            'qr_token_hash' => hash('sha256', $token),
            'qr_token_encrypted' => Crypt::encryptString($token),
            'is_active' => true,
        ]);

        $this->get(route('public.menu.show', [$restaurant->slug, $menu->slug]).'?table='.urlencode($token))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Menu/Public')
                ->where('tableContext.name', 'Table 07')
                ->where('tableContext.token', $token));

        $product = Product::query()->where('restaurant_id', $restaurant->id)->firstOrFail();
        $payload = [
            'customer_name' => 'Table Customer',
            'customer_phone' => '01000000000',
            'fulfillment_type' => 'dine_in',
            'table_token' => $token,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ];

        $this->postJson(route('public.orders.store', [$restaurant->slug, $menu->slug]), $payload, ['Idempotency-Key' => 'table-order-test-key-001'])
            ->assertCreated()
            ->assertJsonPath('order.table_name', 'Table 07');

        $order = Order::query()->where('customer_name', 'Table Customer')->firstOrFail();
        $this->assertSame('dine_in', $order->fulfillment_type);
        $this->assertSame($table->id, $order->restaurant_table_id);
        $this->assertSame('Table 07', $order->restaurant_table_name);

        $table->update(['name' => 'Renamed Table']);
        $this->assertSame('Table 07', $order->fresh()->restaurant_table_name);
    }

    public function test_dine_in_rejects_invalid_or_inactive_table_tokens(): void
    {
        [$restaurant, $menu] = $this->makeMenu();
        $token = Str::random(64);
        $table = RestaurantTable::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Table 08',
            'qr_token_hash' => hash('sha256', $token),
            'qr_token_encrypted' => Crypt::encryptString($token),
            'is_active' => true,
        ]);
        $route = route('public.orders.store', [$restaurant->slug, $menu->slug]);
        $product = Product::query()->where('restaurant_id', $restaurant->id)->firstOrFail();
        $payload = ['customer_name' => 'Invalid Table', 'customer_phone' => '01000000000', 'fulfillment_type' => 'dine_in', 'table_token' => 'wrong-token-value', 'items' => [['product_id' => $product->id, 'quantity' => 1]]];

        $this->postJson($route, $payload)->assertUnprocessable();
        $payload['table_token'] = $token;
        $table->update(['is_active' => false]);
        $this->postJson($route, $payload)->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_required_modifier_must_be_selected_before_order_is_created(): void
    {
        [$restaurant, $menu] = $this->makeMenu();
        $product = Product::query()->where('restaurant_id', $restaurant->id)->firstOrFail();
        Modifier::create([
            'restaurant_id' => $restaurant->id,
            'product_id' => $product->id,
            'name' => 'اختيار الخبز',
            'price_delta' => 0,
            'is_required' => true,
            'is_active' => true,
        ]);

        $this->postJson(route('public.orders.store', [$restaurant->slug, $menu->slug]), [
            'customer_name' => 'Test Customer',
            'customer_phone' => '01000000000',
            'fulfillment_type' => 'pickup',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertUnprocessable()->assertJsonPath('message', 'من فضلك اختار كل الإضافات المطلوبة للمنتج.');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_modifier_choice_group_enforces_minimum_and_maximum_selections_on_server(): void
    {
        [$restaurant, $menu] = $this->makeMenu();
        $product = Product::query()->where('restaurant_id', $restaurant->id)->firstOrFail();
        $sauce = Modifier::create(['restaurant_id' => $restaurant->id, 'product_id' => $product->id, 'name' => 'Garlic', 'price_delta' => 5, 'group_name' => 'Sauce', 'group_min_choices' => 1, 'group_max_choices' => 1, 'is_active' => true]);
        $hot = Modifier::create(['restaurant_id' => $restaurant->id, 'product_id' => $product->id, 'name' => 'Hot', 'price_delta' => 0, 'group_name' => 'Sauce', 'group_min_choices' => 1, 'group_max_choices' => 1, 'is_active' => true]);

        $payload = ['customer_name' => 'Group Customer', 'customer_phone' => '01000000000', 'fulfillment_type' => 'pickup', 'items' => [['product_id' => $product->id, 'quantity' => 1]]];
        $this->postJson(route('public.orders.store', [$restaurant->slug, $menu->slug]), $payload)->assertUnprocessable();
        $payload['items'][0]['modifier_ids'] = [$sauce->id, $hot->id];
        $this->postJson(route('public.orders.store', [$restaurant->slug, $menu->slug]), $payload)->assertUnprocessable();
        $payload['items'][0]['modifier_ids'] = [$sauce->id];
        $this->postJson(route('public.orders.store', [$restaurant->slug, $menu->slug]), $payload)->assertCreated()->assertJsonPath('order.total', '105.00');
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_idempotency_key_replays_the_same_order_and_rejects_changed_payload(): void
    {
        [$restaurant, $menu] = $this->makeMenu();
        $product = Product::query()->where('restaurant_id', $restaurant->id)->firstOrFail();
        $payload = ['customer_name' => 'Retry Customer', 'customer_phone' => '01000000000', 'fulfillment_type' => 'pickup', 'items' => [['product_id' => $product->id, 'quantity' => 1]]];
        $headers = ['Idempotency-Key' => 'qr-checkout-unique-0001'];

        $first = $this->postJson(route('public.orders.store', [$restaurant->slug, $menu->slug]), $payload, $headers)
            ->assertCreated()->assertJsonPath('replayed', false);
        $retry = $this->postJson(route('public.orders.store', [$restaurant->slug, $menu->slug]), $payload, $headers)
            ->assertOk()->assertJsonPath('replayed', true);

        $this->assertSame($first->json('order.number'), $retry->json('order.number'));
        $this->assertSame($first->json('order.tracking_url'), $retry->json('order.tracking_url'));
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseCount('order_integration_events', 1);
        $this->assertDatabaseHas('order_integration_events', ['restaurant_id' => $restaurant->id, 'event_type' => 'order.created', 'status' => 'pending']);

        $payload['items'][0]['quantity'] = 2;
        $this->postJson(route('public.orders.store', [$restaurant->slug, $menu->slug]), $payload, $headers)->assertStatus(409);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_same_product_can_be_ordered_as_separate_lines_with_different_modifier_snapshots(): void
    {
        [$restaurant, $menu] = $this->makeMenu();
        $product = Product::query()->where('restaurant_id', $restaurant->id)->firstOrFail();
        $required = Modifier::create([
            'restaurant_id' => $restaurant->id,
            'product_id' => $product->id,
            'name' => 'حجم كبير',
            'price_delta' => 10,
            'is_required' => true,
            'is_active' => true,
        ]);
        $optional = Modifier::create([
            'restaurant_id' => $restaurant->id,
            'product_id' => $product->id,
            'name' => 'جبنة إضافية',
            'price_delta' => 15,
            'is_required' => false,
            'is_active' => true,
        ]);

        $this->postJson(route('public.orders.store', [$restaurant->slug, $menu->slug]), [
            'customer_name' => 'Test Customer',
            'customer_phone' => '01000000000',
            'fulfillment_type' => 'pickup',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'modifier_ids' => [$required->id]],
                ['product_id' => $product->id, 'quantity' => 1, 'modifier_ids' => [$required->id, $optional->id]],
            ],
        ])->assertCreated()->assertJsonPath('order.total', '235.00');

        $order = Order::query()->where('customer_name', 'Test Customer')->firstOrFail();
        $this->assertDatabaseCount('order_items', 2);
        $this->assertSame(['حجم كبير'], array_column($order->items()->firstOrFail()->modifiers, 'name'));
        $this->assertSame(['حجم كبير', 'جبنة إضافية'], array_column($order->items()->latest('id')->firstOrFail()->modifiers, 'name'));
    }

    public function test_ai_waiter_recommends_only_available_products_from_the_published_restaurant_menu(): void
    {
        [$restaurant, $menu] = $this->makeMenu();
        $product = Product::query()->where('restaurant_id', $restaurant->id)->firstOrFail();
        $unavailable = Product::create([
            'restaurant_id' => $restaurant->id,
            'menu_category_id' => $product->menu_category_id,
            'name' => 'Hidden Special',
            'slug' => 'hidden-special',
            'price' => 50,
            'is_available' => false,
        ]);

        $this->postJson(route('public.ai-waiter.chat', [$restaurant->slug, $menu->slug]), ['message' => 'عايز برجر'])
            ->assertOk()
            ->assertJsonPath('can_order', true)
            ->assertJsonPath('products.0.id', $product->id)
            ->assertJsonMissing(['name' => $unavailable->name])
            ->assertJsonStructure(['reply', 'products', 'can_order', 'disclaimer']);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_ai_waiter_cannot_access_unpublished_or_another_restaurants_menu(): void
    {
        [$restaurant, $menu] = $this->makeMenu(false);
        $this->postJson(route('public.ai-waiter.chat', [$restaurant->slug, $menu->slug]), ['message' => 'اقترحلي حاجة'])
            ->assertNotFound();

        [$otherRestaurant, $otherMenu] = $this->makeMenu(true, 'Other Restaurant', 'other-ai');
        $this->postJson(route('public.ai-waiter.chat', [$restaurant->slug, $otherMenu->slug]), ['message' => 'اقترحلي حاجة'])
            ->assertNotFound();
    }

    public function test_ai_waiter_validates_message_length_and_presence(): void
    {
        [$restaurant, $menu] = $this->makeMenu();
        $route = route('public.ai-waiter.chat', [$restaurant->slug, $menu->slug]);
        $this->postJson($route, [])->assertUnprocessable()->assertJsonValidationErrors('message');
        $this->postJson($route, ['message' => str_repeat('x', 1001)])->assertUnprocessable()->assertJsonValidationErrors('message');
    }

    private function makeMenu(bool $published = true, string $restaurantName = 'Public Restaurant', string $restaurantSlug = 'public-restaurant'): array
    {
        $restaurant = Restaurant::create(['name' => $restaurantName, 'slug' => $restaurantSlug, 'is_active' => true]);
        app(TenantContext::class)->set($restaurant);

        $template = Template::create(['key' => 'fast-food-'.$restaurantSlug, 'name' => 'Fast Food', 'is_active' => true]);
        $version = $template->createVersion(['layout' => 'original'], true);
        $menu = Menu::create([
            'restaurant_id' => $restaurant->id,
            'template_id' => $template->id,
            'template_version_id' => $version->id,
            'name' => $restaurantName === 'Other Restaurant' ? 'Other Menu' : 'Lunch Menu',
            'slug' => 'lunch',
            'is_published' => $published,
        ]);

        $category = MenuCategory::create([
            'restaurant_id' => $restaurant->id,
            'menu_id' => $menu->id,
            'name' => 'Burgers',
            'slug' => 'burgers',
            'is_active' => true,
        ]);
        Product::create([
            'restaurant_id' => $restaurant->id,
            'menu_category_id' => $category->id,
            'name' => 'Classic Burger',
            'slug' => 'classic-burger',
            'price' => 100,
            'is_available' => true,
        ]);

        return [$restaurant, $menu, $version];
    }
}
