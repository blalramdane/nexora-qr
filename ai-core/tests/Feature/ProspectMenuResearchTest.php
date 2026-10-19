<?php

namespace Tests\Feature;

use App\Models\DemoWorkspace;
use App\Models\LocalBusiness;
use App\Models\ProspectMenuResearch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProspectMenuResearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_scout_shows_the_online_menu_research_panel_and_requires_a_platform_owner(): void
    {
        $this->get('/local-scout')->assertRedirect('/login');
        $user = $this->platformOwner('prospect-menu-ui@example.test');
        $business = $this->business('prospect-menu-ui', 'https://restaurant.example.test/');
        $research = ProspectMenuResearch::create([
            'local_business_id' => $business->id,
            'created_by' => $user->id,
            'status' => 'needs_review',
            'source_website_url' => 'https://restaurant.example.test',
            'discovered_links' => [['url' => 'https://restaurant.example.test/menu', 'label' => 'المنيو', 'kind' => 'html', 'host' => 'restaurant.example.test']],
            'extracted_data' => ['categories' => [['name' => 'برجر', 'items' => [['name' => 'برجر كلاسيك', 'price' => 85]]]]],
            'discovered_at' => now(),
        ]);

        $this->actingAs($user)->get('/local-scout')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Local/Scout')
            ->where('provider.name', 'Google Places API')
            ->where('businesses.data.0.menu_researches.0.id', $research->id)
            ->where('businesses.data.0.menu_researches.0.status', 'needs_review'));
    }

    public function test_official_website_discovery_records_candidate_links_without_publishing_or_contacting(): void
    {
        $this->fakeOfficialSite();
        $user = $this->platformOwner('prospect-discovery@example.test');
        $business = $this->business('prospect-discovery', 'https://restaurant.example.test/');

        $response = $this->actingAs($user)->postJson('/local-scout/businesses/'.$business->id.'/menu-research');

        $response->assertCreated()
            ->assertJsonPath('status', 'links_found')
            ->assertJsonPath('candidates.0.url', 'https://restaurant.example.test/menu');

        $researchId = $response->json('research_id');
        $this->assertDatabaseHas('prospect_menu_researches', [
            'id' => $researchId,
            'local_business_id' => $business->id,
            'status' => 'links_found',
            'source_website_url' => 'https://restaurant.example.test',
        ]);
        $this->assertDatabaseHas('local_businesses', ['id' => $business->id, 'listing_status' => 'draft', 'lead_status' => 'needs_verification']);
        $this->assertNull($business->fresh()->last_contacted_at);
        Http::assertSent(fn ($request) => $request->url() === 'https://restaurant.example.test/robots.txt');
    }

    public function test_official_menu_discovery_rejects_missing_or_non_https_official_websites(): void
    {
        Http::preventStrayRequests();
        $user = $this->platformOwner('prospect-security@example.test');
        $business = $this->business('prospect-no-site', null);

        $this->actingAs($user)->postJson('/local-scout/businesses/'.$business->id.'/menu-research')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'أضف رابط الموقع الرسمي للمطعم وتأكد منه قبل البحث عن المنيو.');

        $business->update(['website_url' => 'http://127.0.0.1/admin']);
        $this->actingAs($user)->postJson('/local-scout/businesses/'.$business->id.'/menu-research')->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_external_menu_links_are_not_added_as_candidates(): void
    {
        Http::fake([
            'https://restaurant.example.test/robots.txt' => Http::response('', 404),
            'https://restaurant.example.test' => Http::response('<html><body><a href="https://other.example.test/menu.pdf">قائمة الطعام</a><a href="/menu">المنيو</a></body></html>', 200, ['Content-Type' => 'text/html; charset=utf-8']),
        ]);
        $user = $this->platformOwner('prospect-same-host@example.test');
        $business = $this->business('prospect-same-host', 'https://restaurant.example.test/');

        $this->actingAs($user)->postJson('/local-scout/businesses/'.$business->id.'/menu-research')
            ->assertCreated()
            ->assertJsonCount(1, 'candidates')
            ->assertJsonPath('candidates.0.url', 'https://restaurant.example.test/menu');
    }

    public function test_confirmed_official_site_creates_private_prospect_and_reuses_place_id_without_duplicates(): void
    {
        Http::fake([
            'https://restaurant.example.test/robots.txt' => Http::response('', 404),
            'https://restaurant.example.test' => Http::response('<html><head><meta property="og:site_name" content="مطعم التجربة"></head><body><a href="/menu">المنيو</a></body></html>', 200, ['Content-Type' => 'text/html; charset=utf-8']),
        ]);
        $user = $this->platformOwner('official-site-intake@example.test');
        $payload = [
            'source_place_id' => 'ChIJtest_place_12345',
            'website_url' => 'https://restaurant.example.test/',
            'city' => 'الزرقا',
        ];

        $first = $this->actingAs($user)->postJson('/local-scout/official-site-intake', $payload)
            ->assertCreated()
            ->assertJsonPath('business_name', 'مطعم التجربة')
            ->assertJsonPath('status', 'links_found');

        $businessId = $first->json('business_id');
        $researchId = $first->json('research_id');
        $this->assertDatabaseHas('local_businesses', [
            'id' => $businessId,
            'name' => 'مطعم التجربة',
            'source_place_id' => 'ChIJtest_place_12345',
            'source' => 'official_website_verified',
            'listing_status' => 'draft',
            'lead_status' => 'needs_verification',
            'phone' => null,
            'address' => null,
        ]);
        $this->assertDatabaseHas('prospect_menu_researches', ['id' => $researchId, 'local_business_id' => $businessId]);
        $this->assertNull(LocalBusiness::findOrFail($businessId)->last_contacted_at);

        $second = $this->actingAs($user)->postJson('/local-scout/official-site-intake', $payload)
            ->assertOk()
            ->assertJsonPath('business_id', $businessId)
            ->assertJsonPath('research_id', $researchId)
            ->assertJsonPath('business_created', false);

        $this->assertDatabaseCount('local_businesses', 1);
        $this->assertDatabaseCount('prospect_menu_researches', 1);
    }

    public function test_official_site_intake_does_not_create_a_lead_when_official_name_cannot_be_verified(): void
    {
        Http::fake([
            'https://restaurant.example.test/robots.txt' => Http::response('', 404),
            'https://restaurant.example.test' => Http::response('<html><head><title>Home</title></head><body>No restaurant identity</body></html>', 200, ['Content-Type' => 'text/html; charset=utf-8']),
        ]);
        $user = $this->platformOwner('official-site-no-name@example.test');

        $this->actingAs($user)->postJson('/local-scout/official-site-intake', [
            'source_place_id' => 'ChIJtest_place_12345',
            'website_url' => 'https://restaurant.example.test',
            'city' => 'الزرقا',
        ])->assertUnprocessable();

        $this->assertDatabaseCount('local_businesses', 0);
        $this->assertDatabaseCount('prospect_menu_researches', 0);
    }

    public function test_menu_candidate_requires_configured_ai_and_records_the_blocker_without_inventing_items(): void
    {
        $this->fakeOfficialSite();
        config(['services.openai.api_key' => null]);
        $user = $this->platformOwner('prospect-no-ai@example.test');
        $business = $this->business('prospect-no-ai', 'https://restaurant.example.test/');
        $researchResponse = $this->actingAs($user)->postJson('/local-scout/businesses/'.$business->id.'/menu-research')->assertCreated();
        $researchId = $researchResponse->json('research_id');

        $this->actingAs($user)->postJson('/local-scout/menu-research/'.$researchId.'/extract', [
            'candidate_url' => 'https://restaurant.example.test/menu',
        ])->assertUnprocessable()
            ->assertJsonPath('status', 'awaiting_ai_config')
            ->assertJsonPath('needs_provider_setup', true);

        $this->assertDatabaseHas('prospect_menu_researches', ['id' => $researchId, 'status' => 'awaiting_ai_config']);
        $this->assertDatabaseCount('demo_workspaces', 0);
    }

    public function test_reviewed_research_can_seed_one_private_demo_with_the_reviewed_menu_snapshot(): void
    {
        $this->fakeOfficialSite();
        config(['services.openai.api_key' => 'test-api-key', 'services.openai.base_url' => 'https://api.openai.com/v1']);
        Http::fake([
            'https://restaurant.example.test/robots.txt' => Http::response('', 404),
            'https://restaurant.example.test' => Http::response('<html><body><a href="/menu">المنيو</a></body></html>', 200, ['Content-Type' => 'text/html; charset=utf-8']),
            'https://restaurant.example.test/menu' => Http::response('<html><body><h1>المنيو</h1><p>برجر كلاسيك 85 جنيه</p></body></html>', 200, ['Content-Type' => 'text/html; charset=utf-8']),
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => json_encode(['categories' => [[
                    'name' => 'برجر',
                    'items' => [['name' => 'برجر كلاسيك', 'price' => 85, 'description' => null, 'uncertain' => false]],
                ]]], JSON_UNESCAPED_UNICODE)]]],
            ], 200),
        ]);

        $user = $this->platformOwner('prospect-demo@example.test');
        $business = $this->business('prospect-demo', 'https://restaurant.example.test/');
        $researchId = $this->actingAs($user)->postJson('/local-scout/businesses/'.$business->id.'/menu-research')->assertCreated()->json('research_id');

        $this->actingAs($user)->postJson('/local-scout/menu-research/'.$researchId.'/extract', [
            'candidate_url' => 'https://restaurant.example.test/menu',
        ])->assertOk()->assertJsonPath('categories.0.items.0.name', 'برجر كلاسيك');
        Http::assertSent(fn ($request) => $request->url() === 'https://api.openai.com/v1/chat/completions' && $request['model'] === 'gpt-4o-mini');

        $this->actingAs($user)->postJson('/local-scout/menu-research/'.$researchId.'/review', [
            'categories' => [['name' => 'برجر', 'items' => [['name' => 'برجر كلاسيك', 'price' => 85]]]],
        ])->assertOk()->assertJsonPath('status', 'reviewed');

        $createResponse = $this->actingAs($user)->postJson('/local-scout/businesses/'.$business->id.'/demo', [
            'style' => 'modern', 'menu_research_id' => $researchId,
        ])->assertCreated()->assertJsonPath('invitation_id', null);

        $workspace = DemoWorkspace::query()->findOrFail($createResponse->json('workspace_id'));
        $this->assertSame('برجر كلاسيك', $workspace->menu_snapshot['items'][0]['name']);
        $this->assertSame(85.0, (float) $workspace->menu_snapshot['items'][0]['price']);
        $this->assertTrue($workspace->menu_snapshot['source']['requires_owner_confirmation']);
        $this->assertSame('demo_ready', ProspectMenuResearch::findOrFail($researchId)->status);

        $this->actingAs($user)->postJson('/local-scout/businesses/'.$business->id.'/demo', [
            'style' => 'modern', 'menu_research_id' => $researchId,
        ])->assertUnprocessable();
        $this->assertDatabaseCount('demo_workspaces', 1);
    }

    private function fakeOfficialSite(): void
    {
        Http::fake([
            'https://restaurant.example.test/robots.txt' => Http::response('', 404),
            'https://restaurant.example.test' => Http::response('<html><body><a href="/menu">المنيو</a><a href="https://untrusted.example.test/menu.pdf">قائمة أخرى</a></body></html>', 200, ['Content-Type' => 'text/html; charset=utf-8']),
            'https://restaurant.example.test/menu' => Http::response('<html><body><h1>المنيو</h1><p>برجر كلاسيك 85 جنيه</p></body></html>', 200, ['Content-Type' => 'text/html; charset=utf-8']),
        ]);
    }

    private function business(string $slug, ?string $websiteUrl): LocalBusiness
    {
        return LocalBusiness::create([
            'name' => 'مطعم اختبار '.$slug,
            'slug' => $slug,
            'city' => 'الزرقا',
            'category' => 'مطعم',
            'website_url' => $websiteUrl,
            'listing_status' => 'draft',
            'lead_status' => 'needs_verification',
            'menu_status' => 'unknown',
        ]);
    }

    private function platformOwner(string $email): User
    {
        $user = User::create(['name' => 'NEXORA Research Tester', 'email' => $email, 'password' => 'test-password']);
        $user->forceFill(['is_platform_owner' => true])->save();

        return $user;
    }
}
