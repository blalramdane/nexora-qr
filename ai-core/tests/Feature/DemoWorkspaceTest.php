<?php

namespace Tests\Feature;

use App\Models\DemoInvitation;
use App\Models\DemoWorkspace;
use App\Models\LocalBusiness;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_owner_can_create_personalized_links_with_separate_preview_and_owner_permissions(): void
    {
        $user = $this->platformOwner();
        $business = LocalBusiness::create(['name' => 'مطعم تجربة', 'slug' => 'demo-test-restaurant', 'city' => 'الزرقا', 'category' => 'مطعم']);

        $response = $this->actingAs($user)->postJson("/local-scout/businesses/{$business->id}/demo", [
            'style' => 'modern', 'request_note' => 'واجهة داكنة مع لون أخضر',
        ])->assertCreated()->assertJsonStructure(['workspace_id', 'preview_url', 'owner_url', 'expires_at', 'message']);

        $payload = $response->json();
        $previewToken = basename(parse_url($payload['preview_url'], PHP_URL_PATH));
        $ownerToken = basename(parse_url($payload['owner_url'], PHP_URL_PATH));
        $workspace = DemoWorkspace::findOrFail($payload['workspace_id']);

        $this->assertNotSame($previewToken, $ownerToken);
        $this->assertSame(hash('sha256', $previewToken), $workspace->preview_token_hash);
        $this->assertSame(hash('sha256', $ownerToken), $workspace->owner_token_hash);
        $this->assertStringNotContainsString($previewToken, json_encode($workspace->getAttributes()));
        $this->assertSame('needs_verification', $business->fresh()->lead_status);
        $this->assertNull($business->fresh()->last_contacted_at);
        $this->assertDatabaseHas('local_business_activities', [
            'local_business_id' => $business->id,
            'type' => 'demo',
            'outcome' => null,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)->get('/local-scout')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Local/Scout')->where('businesses.data.0.demo_workspaces.0.id', $workspace->id)
            ->where('businesses.data.0.demo_workspaces.0.is_usable', true));

        $previewResponse = $this->get($payload['preview_url']);
        $previewResponse->assertOk()->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringContainsString('no-store', $previewResponse->headers->get('Cache-Control', ''));
        $previewResponse->assertInertia(fn ($page) => $page
            ->component('Demo/Experience')->where('accessMode', 'preview')->where('workspace.name', 'مطعم تجربة'));
        $ownerResponse = $this->get($payload['owner_url']);
        $ownerResponse->assertOk()->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringContainsString('no-store', $ownerResponse->headers->get('Cache-Control', ''));
        $ownerResponse->assertInertia(fn ($page) => $page
            ->component('Demo/Experience')->where('accessMode', 'owner'));
    }

    public function test_test_orders_are_stored_only_in_the_demo_sandbox_and_preview_token_cannot_edit_menu(): void
    {
        $user = $this->platformOwner();
        $business = LocalBusiness::create(['name' => 'Sandbox Restaurant', 'slug' => 'sandbox-restaurant', 'city' => 'دمياط', 'category' => 'مطعم']);
        $payload = $this->actingAs($user)->postJson("/local-scout/businesses/{$business->id}/demo", [])->assertCreated()->json();
        $previewToken = basename(parse_url($payload['preview_url'], PHP_URL_PATH));
        $ownerToken = basename(parse_url($payload['owner_url'], PHP_URL_PATH));

        $this->postJson("/demo/{$previewToken}/orders", [
            'customer_name' => 'عميل تجريبي', 'customer_phone' => '01000000000',
            'items' => [['product_id' => 'sample-1', 'quantity' => 2]],
        ])->assertCreated()->assertJsonPath('order.total', 240)->assertJsonPath('order.status', 'new');

        $this->assertDatabaseCount('demo_sandbox_orders', 1);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('demo_sandbox_orders', ['demo_workspace_id' => $payload['workspace_id'], 'total' => 240]);

        $this->patchJson("/demo/{$previewToken}/menu", ['items' => []])->assertForbidden();
        $this->postJson("/demo/{$ownerToken}/orders", [
            'customer_name' => 'عميل تجريبي', 'customer_phone' => '01000000000',
            'items' => [['product_id' => 'sample-1', 'quantity' => 1]],
        ])->assertForbidden();
    }

    public function test_owner_menu_changes_and_order_status_updates_remain_inside_the_sandbox(): void
    {
        $user = $this->platformOwner();
        $business = LocalBusiness::create(['name' => 'Sandbox Editing', 'slug' => 'sandbox-editing', 'city' => 'الزرقا', 'category' => 'مطعم']);
        $payload = $this->actingAs($user)->postJson("/local-scout/businesses/{$business->id}/demo", [])->assertCreated()->json();
        $workspace = DemoWorkspace::findOrFail($payload['workspace_id']);
        $ownerToken = basename(parse_url($payload['owner_url'], PHP_URL_PATH));
        $previewToken = basename(parse_url($payload['preview_url'], PHP_URL_PATH));

        $items = $workspace->menu_snapshot['items'];
        $items[0]['name'] = 'صنف بسعر معدل';
        $items[0]['price'] = 150;
        $this->patchJson("/demo/{$ownerToken}/menu", ['items' => $items])->assertOk()->assertJsonPath('menu.items.0.name', 'صنف بسعر معدل');

        $orderResponse = $this->postJson("/demo/{$previewToken}/orders", [
            'customer_name' => 'عميل', 'customer_phone' => '01000000000', 'items' => [['product_id' => $items[0]['id'], 'quantity' => 1]],
        ])->assertCreated()->assertJsonPath('order.total', 150);
        $number = $orderResponse->json('order.number');
        $order = $workspace->orders()->where('order_number', $number)->firstOrFail();

        $this->patchJson("/demo/{$ownerToken}/orders/{$order->id}/status", ['status' => 'preparing'])
            ->assertOk()->assertJsonPath('status', 'preparing');
        $this->assertDatabaseHas('demo_sandbox_orders', ['id' => $order->id, 'status' => 'preparing']);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_expired_and_revoked_demo_links_are_unavailable(): void
    {
        $user = $this->platformOwner();
        $business = LocalBusiness::create(['name' => 'Expiring Demo', 'slug' => 'expiring-demo', 'city' => 'دمياط', 'category' => 'مطعم']);
        $payload = $this->actingAs($user)->postJson("/local-scout/businesses/{$business->id}/demo", [])->assertCreated()->json();
        $token = basename(parse_url($payload['preview_url'], PHP_URL_PATH));
        DemoWorkspace::findOrFail($payload['workspace_id'])->update(['expires_at' => now()->subMinute()]);
        $this->get("/demo/{$token}")->assertGone();

        $payload2 = $this->postJson("/local-scout/businesses/{$business->id}/demo", [])->assertCreated()->json();
        $token2 = basename(parse_url($payload2['preview_url'], PHP_URL_PATH));
        $this->postJson("/local-scout/demos/{$payload2['workspace_id']}/revoke")->assertOk()->assertJsonPath('revoked', true);
        $this->get("/demo/{$token2}")->assertGone();
    }

    public function test_invitation_shows_only_public_business_context_and_records_request_in_local_scout(): void
    {
        $user = $this->platformOwner();
        $business = LocalBusiness::create([
            'name' => 'مطعم طلب الاختيار', 'slug' => 'choice-request-restaurant',
            'city' => 'الزرقا', 'area' => 'وسط البلد', 'category' => 'مطعم',
            'internal_notes' => 'سر داخلي لا يظهر في الرابط',
        ]);

        $payload = $this->actingAs($user)->postJson("/local-scout/businesses/{$business->id}/demo-invitations", [])
            ->assertCreated()
            ->assertJsonStructure(['invitation_id', 'request_url', 'expires_at', 'message'])
            ->assertJsonMissingPath('owner_url')->json();

        $token = basename(parse_url($payload['request_url'], PHP_URL_PATH));
        $invitation = DemoInvitation::findOrFail($payload['invitation_id']);
        $this->assertSame(hash('sha256', $token), $invitation->token_hash);
        $this->assertStringNotContainsString($token, json_encode($invitation->getAttributes()));

        $requestResponse = $this->get($payload['request_url']);
        $requestResponse->assertOk()->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringContainsString('no-store', $requestResponse->headers->get('Cache-Control', ''));
        $requestResponse->assertInertia(fn ($page) => $page
            ->component('Demo/Request')
            ->where('business.name', 'مطعم طلب الاختيار')
            ->missing('business.internal_notes')
            ->missing('owner_url'));

        $this->postJson("/demo-request/{$token}", [
            'mode' => 'custom_preview',
            'style' => 'luxury',
            'features' => ['digital_menu', 'order_dashboard', 'whatsapp'],
            'note' => 'عايز ألوان الهوية الرسمية',
        ])->assertCreated()->assertJsonPath('submitted', true);

        $this->assertDatabaseHas('demo_invitations', [
            'id' => $invitation->id, 'status' => 'requested', 'requested_mode' => 'custom_preview', 'style' => 'luxury',
        ]);
        $this->assertDatabaseHas('local_business_activities', [
            'local_business_id' => $business->id, 'type' => 'demo', 'outcome' => 'demo_booked',
        ]);
        $this->assertSame('demo_booked', $business->fresh()->lead_status);
        $this->assertNull($business->fresh()->last_contacted_at);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('demo_sandbox_orders', 0);
        $this->actingAs($user)->get('/local-scout')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Local/Scout')
            ->where('businesses.data.0.demo_invitations.0.id', $invitation->id)
            ->where('businesses.data.0.demo_invitations.0.status', 'requested'));

        $this->postJson("/demo-request/{$token}", [
            'mode' => 'customer_preview', 'style' => 'modern', 'features' => [],
        ])->assertStatus(409);

        $demoPayload = $this->postJson("/local-scout/businesses/{$business->id}/demo", [
            'invitation_id' => $invitation->id,
        ])->assertCreated()->assertJsonPath('invitation_id', $invitation->id)->json();
        $workspace = DemoWorkspace::findOrFail($demoPayload['workspace_id']);
        $this->assertSame('demo_ready', $invitation->fresh()->status);
        $this->assertSame($workspace->id, $invitation->fresh()->demo_workspace_id);
        $this->assertSame('luxury', $workspace->menu_snapshot['style']);
        $this->assertSame('عايز ألوان الهوية الرسمية', $workspace->preview_request['note']);
        $this->assertSame('custom_preview', $workspace->preview_request['requested_mode']);
        $this->assertSame(['digital_menu', 'order_dashboard', 'whatsapp'], $workspace->preview_request['requested_features']);
        $this->assertSame($demoPayload['preview_url'], $demoPayload['recommended_url']);
        $this->get($payload['request_url'])->assertGone();
        $this->get($demoPayload['preview_url'])->assertOk();
        $this->get($demoPayload['owner_url'])->assertOk();
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('demo_sandbox_orders', 0);

        $this->postJson("/local-scout/businesses/{$business->id}/demo", [
            'invitation_id' => $invitation->id,
        ])->assertStatus(422);
    }

    public function test_owner_dashboard_choice_recommends_only_the_owner_sandbox_link_for_manual_sharing(): void
    {
        $user = $this->platformOwner();
        $business = LocalBusiness::create(['name' => 'Owner Demo Selection', 'slug' => 'owner-demo-selection', 'city' => 'الزرقا', 'category' => 'مطعم']);
        $invitationPayload = $this->actingAs($user)->postJson("/local-scout/businesses/{$business->id}/demo-invitations", [])->assertCreated()->json();
        $token = basename(parse_url($invitationPayload['request_url'], PHP_URL_PATH));

        $this->postJson("/demo-request/{$token}", [
            'mode' => 'owner_dashboard', 'style' => 'modern', 'features' => ['product_editing', 'order_dashboard'],
        ])->assertCreated();

        $payload = $this->postJson("/local-scout/businesses/{$business->id}/demo", [
            'invitation_id' => $invitationPayload['invitation_id'],
        ])->assertCreated()->json();

        $this->assertNotSame($payload['preview_url'], $payload['owner_url']);
        $this->assertSame($payload['owner_url'], $payload['recommended_url']);
        $this->assertSame('owner_dashboard', DemoWorkspace::findOrFail($payload['workspace_id'])->preview_request['requested_mode']);
        $this->get($payload['recommended_url'])->assertOk()->assertInertia(fn ($page) => $page
            ->component('Demo/Experience')->where('accessMode', 'owner'));
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_demo_invitation_expires_and_platform_owner_can_revoke_it(): void
    {
        $user = $this->platformOwner();
        $business = LocalBusiness::create(['name' => 'Invitation Lifecycle', 'slug' => 'invitation-lifecycle', 'city' => 'دمياط', 'category' => 'مطعم']);
        $payload = $this->actingAs($user)->postJson("/local-scout/businesses/{$business->id}/demo-invitations", [])->assertCreated()->json();
        $token = basename(parse_url($payload['request_url'], PHP_URL_PATH));
        $invitation = DemoInvitation::findOrFail($payload['invitation_id']);

        $invitation->update(['expires_at' => now()->subMinute()]);
        $this->get("/demo-request/{$token}")->assertGone();

        $payload2 = $this->postJson("/local-scout/businesses/{$business->id}/demo-invitations", [])->assertCreated()->json();
        $this->postJson("/local-scout/demo-invitations/{$payload2['invitation_id']}/revoke")
            ->assertOk()->assertJsonPath('revoked', true);
        $token2 = basename(parse_url($payload2['request_url'], PHP_URL_PATH));
        $this->get("/demo-request/{$token2}")->assertGone();
    }

    public function test_non_platform_user_cannot_create_or_revoke_demo_invitations(): void
    {
        $owner = $this->platformOwner();
        $business = LocalBusiness::create(['name' => 'Protected Invitation', 'slug' => 'protected-invitation', 'city' => 'دمياط', 'category' => 'مطعم']);
        $payload = $this->actingAs($owner)->postJson("/local-scout/businesses/{$business->id}/demo-invitations", [])->assertCreated()->json();

        $ordinaryUser = User::create(['name' => 'Regular User', 'email' => 'regular-'.uniqid().'@example.test', 'password' => 'test-password']);
        $this->actingAs($ordinaryUser)->postJson("/local-scout/businesses/{$business->id}/demo-invitations", [])->assertForbidden();
        $this->postJson("/local-scout/demo-invitations/{$payload['invitation_id']}/revoke")->assertForbidden();
        $this->assertDatabaseHas('demo_invitations', ['id' => $payload['invitation_id'], 'status' => 'invited']);
    }

    private function platformOwner(): User
    {
        $user = User::create(['name' => 'NEXORA Operator', 'email' => 'operator-'.uniqid().'@example.test', 'password' => 'test-password']);
        $user->forceFill(['is_platform_owner' => true])->save();

        return $user;
    }
}
