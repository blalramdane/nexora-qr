<?php

namespace Tests\Feature;

use App\Models\LocalBusiness;
use App\Models\LocalBusinessActivity;
use App\Models\Menu;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LocalDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_directory_lists_only_published_local_businesses(): void
    {
        $published = LocalBusiness::create([
            'name' => 'مطعم الزرقا', 'slug' => 'zarqa-restaurant', 'city' => 'الزرقا', 'area' => 'وسط البلد',
            'category' => 'مطعم', 'source' => 'official', 'source_url' => 'https://example.com/place',
            'listing_status' => 'published', 'menu_status' => 'missing',
        ]);
        LocalBusiness::create([
            'name' => 'مسودة مخفية', 'slug' => 'hidden-draft', 'city' => 'دمياط', 'category' => 'كافيه',
            'listing_status' => 'draft',
        ]);

        $this->get('/local')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Local/Directory')
            ->where('businesses.total', 1)
            ->where('businesses.data.0.id', $published->id)
            ->where('businesses.data.0.name', 'مطعم الزرقا'));

        $this->get('/local/hidden-draft')->assertNotFound();
        $this->get('/local/zarqa-restaurant')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Local/Business')->where('business.name', 'مطعم الزرقا'));
    }

    public function test_scout_requires_authentication_and_can_add_a_draft_business(): void
    {
        $this->get('/local-scout')->assertRedirect('/login');

        $user = User::create(['name' => 'NEXORA Operator', 'email' => 'scout@example.test', 'password' => 'test-password']);
        $this->actingAs($user)->get('/local-scout')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Local/Scout')->where('stats.all', 0));

        $this->actingAs($user)->post('/local-scout/businesses', [
            'name' => 'كافيه دمياط', 'city' => 'دمياط', 'area' => 'وسط البلد', 'category' => 'كافيه',
            'source_url' => 'https://example.com/cafe', 'menu_status' => 'missing',
        ])->assertRedirect();

        $this->assertDatabaseHas('local_businesses', [
            'name' => 'كافيه دمياط', 'city' => 'دمياط', 'area' => 'وسط البلد',
            'listing_status' => 'draft', 'source' => 'manual', 'menu_status' => 'missing',
        ]);
        $this->get('/local')->assertInertia(fn (Assert $page) => $page->where('businesses.total', 0));
    }

    public function test_scout_refuses_to_publish_business_without_a_source_url(): void
    {
        $user = User::create(['name' => 'NEXORA Operator', 'email' => 'scout2@example.test', 'password' => 'test-password']);
        $business = LocalBusiness::create(['name' => 'Unverified Place', 'slug' => 'unverified-place', 'city' => 'الزرقا', 'category' => 'مطعم']);

        $this->actingAs($user)->patch('/local-scout/businesses/'.$business->id, ['listing_status' => 'published'])
            ->assertSessionHasErrors('listing_status');

        $this->assertDatabaseHas('local_businesses', ['id' => $business->id, 'listing_status' => 'draft']);
    }

    public function test_scout_stores_sales_pipeline_fields_and_updates_contact_timestamp(): void
    {
        $user = User::create(['name' => 'NEXORA Operator', 'email' => 'scout-pipeline@example.test', 'password' => 'test-password']);

        $this->actingAs($user)->post('/local-scout/businesses', [
            'name' => 'مطعم مسار المبيعات',
            'city' => 'الزرقا',
            'category' => 'مطعم',
            'menu_status' => 'unknown',
            'source_url' => 'https://example.com/restaurant',
            'lead_status' => 'verified',
            'confidence_score' => 90,
            'contact_channel' => 'whatsapp',
            'evidence_notes' => 'تمت مراجعة صفحة النشاط الرسمية.',
            'menu_rights_status' => 'permission_requested',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $business = LocalBusiness::query()->latest('id')->firstOrFail();

        $this->assertSame('مطعم مسار المبيعات', $business->name);
        $this->assertSame('verified', $business->lead_status);
        $this->assertSame(90, $business->confidence_score);
        $this->assertSame('whatsapp', $business->contact_channel);
        $this->assertSame('permission_requested', $business->menu_rights_status);
        $this->assertNull($business->last_contacted_at);

        $this->actingAs($user)->patch('/local-scout/businesses/'.$business->id, ['lead_status' => 'contacted'])
            ->assertRedirect();

        $this->assertDatabaseHas('local_businesses', [
            'id' => $business->id,
            'lead_status' => 'contacted',
            'contact_channel' => 'whatsapp',
        ]);
        $this->assertNotNull($business->fresh()->last_contacted_at);

        $this->actingAs($user)->patch('/local-scout/businesses/'.$business->id, [
            'lead_status' => 'demo_booked',
            'next_action_at' => now()->addDay()->toDateTimeString(),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertNotNull($business->fresh()->last_contacted_at);
        $this->assertNotNull($business->fresh()->next_action_at);

        $this->actingAs($user)->get('/local-scout?lead_status=demo_booked')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Local/Scout')
                ->where('businesses.total', 1)
                ->where('businesses.data.0.lead_status', 'demo_booked'));
    }

    public function test_internal_scout_evidence_and_notes_are_not_sent_to_public_directory(): void
    {
        $business = LocalBusiness::create([
            'name' => 'معلومات داخلية فقط',
            'slug' => 'internal-only-local-test',
            'city' => 'الزرقا',
            'category' => 'مطعم',
            'source_url' => 'https://example.com/public-source',
            'listing_status' => 'published',
            'internal_notes' => 'ملاحظة سرية للفريق',
            'evidence_notes' => 'دليل تحقق للفريق',
            'lead_status' => 'verified',
            'confidence_score' => 95,
        ]);

        $this->get('/local/'.$business->slug)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Local/Business')
            ->missing('business.internal_notes')
            ->missing('business.evidence_notes')
            ->missing('business.lead_status')
            ->missing('business.confidence_score')
            ->missing('business.id')
            ->has('business.name'));
    }

    public function test_scout_counts_due_followups_and_prioritizes_them(): void
    {
        $user = User::create(['name' => 'NEXORA Operator', 'email' => 'scout-followups@example.test', 'password' => 'test-password']);

        $due = LocalBusiness::create([
            'name' => 'Due Follow-up',
            'slug' => 'due-follow-up',
            'city' => 'الزرقا',
            'category' => 'مطعم',
            'next_action_at' => now()->subDay(),
            'lead_status' => 'contacted',
        ]);
        LocalBusiness::create([
            'name' => 'Future Follow-up',
            'slug' => 'future-follow-up',
            'city' => 'دمياط',
            'category' => 'مطعم',
            'next_action_at' => now()->addDay(),
            'lead_status' => 'interested',
        ]);
        LocalBusiness::create([
            'name' => 'Closed Lead',
            'slug' => 'closed-lead',
            'city' => 'الزرقا',
            'category' => 'مطعم',
            'next_action_at' => now()->subDay(),
            'lead_status' => 'not_interested',
        ]);

        $this->actingAs($user)->get('/local-scout')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Local/Scout')
            ->where('stats.followups_due', 1)
            ->where('businesses.data.0.id', $due->id));
    }

    public function test_closing_a_lead_clears_its_next_followup(): void
    {
        $user = User::create(['name' => 'NEXORA Operator', 'email' => 'scout-closed@example.test', 'password' => 'test-password']);
        $business = LocalBusiness::create([
            'name' => 'Closed Follow-up',
            'slug' => 'closed-follow-up',
            'city' => 'الزرقا',
            'category' => 'مطعم',
            'lead_status' => 'interested',
            'next_action_at' => now()->subHour(),
        ]);

        $this->actingAs($user)->patch('/local-scout/businesses/'.$business->id, ['lead_status' => 'not_interested'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('local_businesses', [
            'id' => $business->id,
            'lead_status' => 'not_interested',
            'next_action_at' => null,
        ]);
    }

    public function test_scout_records_business_activity_and_updates_sales_stage(): void
    {
        $user = User::create(['name' => 'NEXORA Operator', 'email' => 'scout-activity@example.test', 'password' => 'test-password']);
        $business = LocalBusiness::create([
            'name' => 'مطعم سجل النشاط', 'slug' => 'activity-log-restaurant', 'city' => 'الزرقا',
            'category' => 'مطعم', 'lead_status' => 'contacted', 'next_action_at' => now()->addDay(),
        ]);

        $this->actingAs($user)->post('/local-scout/businesses/'.$business->id.'/activities', [
            'type' => 'demo', 'outcome' => 'demo_booked', 'channel' => 'whatsapp',
            'notes' => 'تم الاتفاق على موعد عرض تجريبي.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('local_business_activities', [
            'local_business_id' => $business->id,
            'user_id' => $user->id,
            'type' => 'demo',
            'outcome' => 'demo_booked',
            'channel' => 'whatsapp',
            'notes' => 'تم الاتفاق على موعد عرض تجريبي.',
        ]);
        $this->assertSame('demo_booked', $business->fresh()->lead_status);
        $this->assertSame('whatsapp', $business->fresh()->contact_channel);
        $this->assertNotNull($business->fresh()->last_contacted_at);
    }

    public function test_scout_shows_recent_orders_status_history_payment_activity_and_performance_for_linked_restaurants(): void
    {
        $user = User::create(['name' => 'Scout Order Viewer', 'email' => 'scout-orders@example.test', 'password' => 'test-password']);
        $restaurant = Restaurant::create(['name' => 'مطعم مربوط', 'slug' => 'scout-linked-restaurant', 'is_active' => true]);
        $business = LocalBusiness::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'مطعم مربوط في Scout', 'slug' => 'scout-linked-business', 'city' => 'الزرقا',
            'category' => 'مطعم', 'lead_status' => 'contacted',
        ]);
        $template = Template::create(['key' => 'scout-orders-template', 'name' => 'Scout template', 'is_active' => true]);
        $version = $template->createVersion(['layout' => []], true);
        $menu = Menu::create([
            'restaurant_id' => $restaurant->id, 'template_id' => $template->id,
            'template_version_id' => $version->id, 'name' => 'منيو Scout', 'slug' => 'scout-orders-menu', 'is_published' => true,
        ]);
        $order = Order::create([
            'restaurant_id' => $restaurant->id, 'menu_id' => $menu->id, 'order_number' => 'NX-SCOUT-001',
            'customer_name' => 'عميل الاختبار', 'customer_phone' => '01000000000', 'status' => 'completed',
            'payment_status' => 'paid', 'payment_method' => 'cash', 'items_count' => 1, 'subtotal' => 150, 'total' => 150,
        ]);
        $order->statusHistory()->create(['from_status' => 'ready', 'to_status' => 'completed', 'changed_by' => $user->id]);
        LocalBusinessActivity::create([
            'local_business_id' => $business->id, 'user_id' => $user->id, 'type' => 'payment_status',
            'outcome' => 'paid', 'channel' => 'qr_menu', 'notes' => 'تم تسجيل دفع الطلب NX-SCOUT-001', 'occurred_at' => now(),
        ]);

        $this->actingAs($user)->get('/local-scout')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Local/Scout')
            ->where('recentOrders.0.order_number', 'NX-SCOUT-001')
            ->where('recentOrders.0.status', 'completed')
            ->where('recentOrders.0.payment_status', 'paid')
            ->where('recentOrders.0.history.0.to_status', 'completed')
            ->where('recentActivities.0.type', 'payment_status')
            ->where('recentActivities.0.business_name', 'مطعم مربوط في Scout')
            ->where('businesses.data.0.order_stats.orders_count', 1)
            ->where('businesses.data.0.order_stats.completed_orders', 1)
            ->where('businesses.data.0.order_stats.paid_revenue', 150));
    }

    public function test_google_discovery_is_disabled_by_default_and_never_makes_an_api_call(): void
    {
        config(['services.google_places.enabled' => false, 'services.google_places.key' => 'test-key']);
        Http::preventStrayRequests();
        $user = User::create(['name' => 'NEXORA Operator', 'email' => 'scout3@example.test', 'password' => 'test-password']);

        $this->actingAs($user)->postJson('/local-scout/discover', ['query' => 'مطاعم', 'city' => 'الزرقا'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'اكتشاف Google Places متوقف افتراضيًا. يلزم إعداد المفتاح وتفعيل الخدمة بموافقة صريحة قبل أي طلب قد يترتب عليه تكلفة.');

        Http::assertNothingSent();
        $this->assertDatabaseCount('local_businesses', 0);
    }

    public function test_google_discovery_returns_a_temporary_preview_without_persisting_provider_content(): void
    {
        config(['services.google_places.enabled' => true, 'services.google_places.key' => 'test-key']);
        Http::fake([
            'places.googleapis.com/v1/places:searchText' => Http::response([
                'places' => [[
                    'id' => 'places/test-place-id',
                    'displayName' => ['text' => 'مطعم تجريبي'],
                    'formattedAddress' => 'وسط البلد، الزرقا، مصر',
                    'googleMapsUri' => 'https://maps.google.com/?q=test-place',
                    'websiteUri' => 'https://restaurant.example.test',
                    'nationalPhoneNumber' => '+20 100 000 0000',
                    'primaryTypeDisplayName' => ['text' => 'مطعم'],
                ]],
            ], 200),
        ]);
        $user = User::create(['name' => 'NEXORA Operator', 'email' => 'scout4@example.test', 'password' => 'test-password']);

        $this->actingAs($user)->postJson('/local-scout/discover', ['query' => 'مطاعم', 'city' => 'الزرقا'])
            ->assertOk()
            ->assertJsonPath('found', 1)
            ->assertJsonPath('saved', 0)
            ->assertJsonPath('attribution', 'Google Maps')
            ->assertJsonPath('places.0.name', 'مطعم تجريبي');

        Http::assertSent(fn ($request) => $request->url() === 'https://places.googleapis.com/v1/places:searchText'
            && $request->hasHeader('X-Goog-Api-Key', 'test-key'));
        $this->assertDatabaseCount('local_businesses', 0);
    }
}
