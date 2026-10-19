<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\Template;
use App\Models\ThemeAbTest;
use App\Models\ThemeAbTestParticipant;
use App\Models\User;
use App\Services\ThemeAbTestingService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ThemeAbTestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_and_manage_a_test_only_for_a_published_menu_in_their_restaurant(): void
    {
        [$owner, $restaurant, $menu] = $this->ownedMenu('A/B Owner', 'ab-owner', true);
        [, $otherRestaurant, $otherMenu] = $this->ownedMenu('A/B Other', 'ab-other', true);
        app(TenantContext::class)->clear();

        $endpoint = '/owner/restaurants/'.$restaurant->id.'/theme-tests';
        $this->actingAs($owner)->get($endpoint)
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Owner/ThemeTests')
            ->where('restaurant.id', $restaurant->id)
            ->has('menus', 1)
            ->where('menus.0.id', $menu->id)
            ->has('tests', 0));

        $this->actingAs($owner)->post($endpoint, [
            'menu_id' => $menu->id,
            'variant_a' => 'modern',
            'variant_b' => 'luxury',
            'traffic_split' => 50,
        ])->assertRedirect($endpoint);

        $test = ThemeAbTest::query()->where('restaurant_id', $restaurant->id)->firstOrFail();
        $this->assertSame('running', $test->status);
        $this->assertSame(50, $test->traffic_split);
        $this->assertNotNull($test->started_at);

        app(TenantContext::class)->clear();
        $this->actingAs($owner)->post($endpoint, [
            'menu_id' => $otherMenu->id,
            'variant_a' => 'modern',
            'variant_b' => 'luxury',
            'traffic_split' => 50,
        ])->assertNotFound();

        app(TenantContext::class)->clear();
        $this->actingAs($owner)->post($endpoint, [
            'menu_id' => $menu->id,
            'variant_a' => 'modern',
            'variant_b' => 'modern',
            'traffic_split' => 50,
        ])->assertSessionHasErrors(['variant_b']);

        app(TenantContext::class)->clear();
        $this->actingAs($owner)->post('/owner/restaurants/'.$otherRestaurant->id.'/theme-tests', [
            'menu_id' => $otherMenu->id,
            'variant_a' => 'modern',
            'variant_b' => 'luxury',
            'traffic_split' => 50,
        ])->assertForbidden();
    }

    public function test_only_one_test_can_run_on_a_menu_and_completed_tests_cannot_restart(): void
    {
        [$owner, $restaurant, $menu] = $this->ownedMenu('A/B State Owner', 'ab-state-owner', true);
        app(TenantContext::class)->clear();
        $endpoint = '/owner/restaurants/'.$restaurant->id.'/theme-tests';
        $this->actingAs($owner)->post($endpoint, ['menu_id' => $menu->id, 'variant_a' => 'modern', 'variant_b' => 'luxury', 'traffic_split' => 50])->assertRedirect();
        $first = ThemeAbTest::query()->where('restaurant_id', $restaurant->id)->firstOrFail();

        app(TenantContext::class)->clear();
        $this->actingAs($owner)->post($endpoint, ['menu_id' => $menu->id, 'variant_a' => 'modern', 'variant_b' => 'playful', 'traffic_split' => 25])->assertSessionHasErrors(['menu_id']);

        app(TenantContext::class)->clear();
        $this->actingAs($owner)->patch($endpoint.'/'.$first->id, ['status' => 'paused'])->assertRedirect($endpoint);
        $this->assertSame('paused', $first->fresh()->status);

        app(TenantContext::class)->clear();
        $this->actingAs($owner)->post($endpoint, ['menu_id' => $menu->id, 'variant_a' => 'modern', 'variant_b' => 'playful', 'traffic_split' => 25])->assertRedirect();
        $second = ThemeAbTest::query()->where('restaurant_id', $restaurant->id)->where('id', '<>', $first->id)->firstOrFail();

        app(TenantContext::class)->clear();
        $this->actingAs($owner)->patch($endpoint.'/'.$first->id, ['status' => 'running'])->assertSessionHasErrors(['status']);

        app(TenantContext::class)->clear();
        $this->actingAs($owner)->patch($endpoint.'/'.$second->id, ['status' => 'completed'])->assertRedirect($endpoint);
        $this->assertSame('completed', $second->fresh()->status);
        $this->assertNotNull($second->fresh()->ended_at);

        app(TenantContext::class)->clear();
        $this->actingAs($owner)->patch($endpoint.'/'.$second->id, ['status' => 'running'])->assertStatus(409);
    }

    public function test_public_menu_assignment_counts_one_impression_and_persists_the_variant_for_a_browser(): void
    {
        [, $restaurant, $menu] = $this->ownedMenu('A/B Public', 'ab-public', true);
        $test = ThemeAbTest::query()->create([
            'restaurant_id' => $restaurant->id,
            'menu_id' => $menu->id,
            'variant_a' => 'modern',
            'variant_b' => 'luxury',
            'status' => 'running',
            'traffic_split' => 0,
            'started_at' => now(),
        ]);
        app(TenantContext::class)->clear();
        $visitor = str_repeat('v', 64);
        $this->withCookie(ThemeAbTestingService::COOKIE_NAME, $visitor)
            ->get(route('public.menu.show', [$restaurant->slug, $menu->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Menu/Public')
                ->where('abTest.id', $test->id)
                ->where('abTest.variant', 'a')
                ->where('abTest.style', 'modern'));

        $this->assertSame(1, $test->fresh()->impressions_a);
        $this->assertSame(0, $test->fresh()->impressions_b);
        $this->assertDatabaseHas('theme_ab_test_participants', [
            'theme_ab_test_id' => $test->id,
            'visitor_token_hash' => hash('sha256', $visitor),
            'variant' => 'a',
        ]);

        app(TenantContext::class)->clear();
        $this->withCookie(ThemeAbTestingService::COOKIE_NAME, $visitor)
            ->get(route('public.menu.show', [$restaurant->slug, $menu->slug]))->assertOk();
        $this->assertSame(1, $test->fresh()->impressions_a);
        $this->assertSame(1, ThemeAbTestParticipant::query()->where('theme_ab_test_id', $test->id)->count());
    }

    public function test_first_order_conversion_is_attributed_once_and_never_crosses_restaurants(): void
    {
        [, $restaurantA, $menuA] = $this->ownedMenu('A/B Conversion A', 'ab-conversion-a', true);
        [, $restaurantB, $menuB] = $this->ownedMenu('A/B Conversion B', 'ab-conversion-b', true);
        $test = ThemeAbTest::query()->create([
            'restaurant_id' => $restaurantA->id,
            'menu_id' => $menuA->id,
            'variant_a' => 'modern',
            'variant_b' => 'luxury',
            'status' => 'running',
            'traffic_split' => 0,
            'started_at' => now(),
        ]);
        $visitor = str_repeat('c', 64);
        $request = Request::create('/', 'GET', [], [ThemeAbTestingService::COOKIE_NAME => $visitor]);
        app(ThemeAbTestingService::class)->assignFor($menuA, $request);
        $participant = ThemeAbTestParticipant::query()->where('theme_ab_test_id', $test->id)->firstOrFail();

        $orderA = $this->makeOrder($restaurantA, $menuA, 'AB-A-ORDER-1');
        app(ThemeAbTestingService::class)->recordConversion($orderA, $visitor);
        app(ThemeAbTestingService::class)->recordConversion($orderA, $visitor);

        $this->assertNotNull($participant->fresh()->converted_at);
        $this->assertSame($orderA->id, $participant->fresh()->order_id);
        $this->assertSame(1, $test->fresh()->conversions_a);

        $orderB = $this->makeOrder($restaurantB, $menuB, 'AB-B-ORDER-1');
        app(ThemeAbTestingService::class)->recordConversion($orderB, $visitor);
        $this->assertSame(1, $test->fresh()->conversions_a);
        $this->assertSame(0, $test->fresh()->conversions_b);
    }

    public function test_conversion_is_still_attributed_after_test_completion_but_never_before_exposure(): void
    {
        [, $restaurant, $menu] = $this->ownedMenu('A/B Completed', 'ab-completed', true);
        $test = ThemeAbTest::query()->create([
            'restaurant_id' => $restaurant->id,
            'menu_id' => $menu->id,
            'variant_a' => 'modern',
            'variant_b' => 'luxury',
            'status' => 'running',
            'traffic_split' => 0,
            'started_at' => now()->subMinute(),
        ]);

        $visitor = str_repeat('z', 64);
        $request = Request::create('/', 'GET', [], [ThemeAbTestingService::COOKIE_NAME => $visitor]);
        app(ThemeAbTestingService::class)->assignFor($menu, $request);
        $participant = ThemeAbTestParticipant::query()->where('theme_ab_test_id', $test->id)->firstOrFail();

        $test->update(['status' => 'completed', 'ended_at' => now()]);
        $lateOrder = $this->makeOrder($restaurant, $menu, 'AB-LATE-ORDER');
        app(ThemeAbTestingService::class)->recordConversion($lateOrder, $visitor);

        $this->assertNotNull($participant->fresh()->converted_at);
        $this->assertSame($lateOrder->id, $participant->fresh()->order_id);
        $this->assertSame(1, $test->fresh()->conversions_a);

        $unknownVisitor = str_repeat('u', 64);
        app(ThemeAbTestingService::class)->recordConversion($this->makeOrder($restaurant, $menu, 'AB-UNKNOWN-VISITOR'), $unknownVisitor);
        $this->assertSame(1, $test->fresh()->conversions_a);
    }

    /** @return array{0:User,1:Restaurant,2:Menu} */
    private function ownedMenu(string $name, string $slug, bool $published): array
    {
        $restaurant = Restaurant::create(['name' => $name, 'slug' => $slug, 'is_active' => true]);
        $owner = User::create(['name' => 'Owner '.$slug, 'email' => $slug.'@example.test', 'password' => 'test-password']);
        $owner->restaurants()->attach($restaurant->id, ['role' => 'owner']);
        app(TenantContext::class)->set($restaurant);
        $template = Template::create(['key' => 'template-'.$slug, 'name' => 'Template '.$slug, 'is_active' => true]);
        $version = $template->createVersion(['layout' => 'fast-food'], true);
        $menu = Menu::create([
            'restaurant_id' => $restaurant->id,
            'template_id' => $template->id,
            'template_version_id' => $version->id,
            'name' => 'Menu '.$slug,
            'slug' => 'menu-'.$slug,
            'theme' => ['primary' => '#166534', 'accent' => '#84cc16', 'background' => '#ffffff', 'foreground' => '#111827', 'radius' => 'xl'],
            'is_published' => $published,
            'published_at' => $published ? now() : null,
        ]);

        return [$owner, $restaurant, $menu];
    }

    private function makeOrder(Restaurant $restaurant, Menu $menu, string $number): Order
    {
        return Order::create([
            'restaurant_id' => $restaurant->id,
            'menu_id' => $menu->id,
            'order_number' => $number,
            'customer_name' => 'A/B conversion',
            'customer_phone' => '01000000000',
            'fulfillment_type' => 'pickup',
            'status' => 'new',
            'payment_method' => 'cash_on_delivery',
            'payment_status' => 'unpaid',
            'items_count' => 1,
            'subtotal' => 10,
            'total' => 10,
        ]);
    }
}
