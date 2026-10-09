<?php

namespace Tests\Feature;

use App\Models\QrBranch;
use App\Models\QrOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class QrOwnerDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function branch(string $slug): QrBranch
    {
        return QrBranch::query()->create([
            'name' => strtoupper($slug),
            'slug' => $slug,
            'code' => strtoupper(str_replace('-', '', $slug)),
            'menu_version' => 1,
        ]);
    }

    private function qrOrder(QrBranch $branch, string $status): QrOrder
    {
        return $branch->orders()->create([
            'source_order_uuid' => (string) Str::uuid(),
            'status' => $status,
            'fulfillment_type' => 'takeaway',
            'subtotal_minor' => 2500,
            'currency' => 'EGP',
            'menu_version' => 1,
            'items_snapshot' => [['source_product_id' => '1', 'name' => 'Meal', 'quantity' => 1, 'unit_price_minor' => 2500, 'line_total_minor' => 2500, 'options' => []]],
            'payload_hash' => hash('sha256', Str::random(32)),
            'submitted_at' => now(),
            'acknowledged_at' => in_array($status, ['imported', 'rejected'], true) ? now() : null,
            'received_at' => in_array($status, ['received', 'imported', 'rejected'], true) ? now() : null,
        ]);
    }

    public function test_owner_dashboard_shows_branch_health_and_qr_workflow_counts(): void
    {
        $branchA = $this->branch('branch-a');
        $branchB = $this->branch('branch-b');
        $branchA->forceFill(['last_seen_at' => now()])->save();
        $this->qrOrder($branchA, 'pending_delivery');
        $this->qrOrder($branchA, 'received');
        $this->qrOrder($branchA, 'imported');
        $this->qrOrder($branchB, 'rejected');

        $owner = User::query()->create([
            'name' => 'QR Owner',
            'email' => 'owner@example.test',
            'password' => Hash::make('a-long-test-password'),
            'qr_role' => 'owner',
        ]);
        $this->actingAs($owner, 'sanctum');

        $this->getJson('/api/qr/v1/owner/dashboard')
            ->assertOk()
            ->assertJsonPath('summary.branches_count', 2)
            ->assertJsonPath('summary.online_branches_count', 1)
            ->assertJsonPath('summary.pending_qr_orders_count', 1)
            ->assertJsonPath('summary.received_by_cashier_count', 1)
            ->assertJsonPath('summary.imported_today_count', 1)
            ->assertJsonPath('summary.rejected_today_count', 1)
            ->assertJsonPath('data_freshness', 'cloud_qr_sync_state');
    }

    public function test_branch_manager_dashboard_is_scoped_to_assigned_branch(): void
    {
        $branchA = $this->branch('branch-a');
        $branchB = $this->branch('branch-b');
        $this->qrOrder($branchA, 'received');
        $this->qrOrder($branchB, 'received');

        $manager = User::query()->create([
            'name' => 'Branch Manager',
            'email' => 'manager@example.test',
            'password' => Hash::make('a-long-test-password'),
            'qr_role' => 'branch_manager',
            'qr_branch_id' => $branchA->id,
        ]);
        $this->actingAs($manager, 'sanctum');

        $this->getJson('/api/qr/v1/owner/dashboard')
            ->assertOk()
            ->assertJsonPath('summary.branches_count', 1)
            ->assertJsonPath('summary.received_by_cashier_count', 1)
            ->assertJsonCount(1, 'branches')
            ->assertJsonCount(1, 'recent_qr_orders')
            ->assertJsonPath('branches.0.slug', 'branch-a');
    }

    public function test_public_menu_and_owner_dashboard_pages_render(): void
    {
        $this->get('/m/branch-a')->assertOk()->assertSee('DIGITAL MENU');
        $this->get('/owner')->assertOk()->assertSee('لوحة متابعة الفروع')->assertSee('qr-owner-token');
    }

    public function test_owner_login_issues_sanctum_token_and_rejects_wrong_password(): void
    {
        User::query()->create([
            'name' => 'QR Owner',
            'email' => 'owner@example.test',
            'password' => Hash::make('a-long-test-password'),
            'qr_role' => 'owner',
        ]);

        $this->postJson('/api/qr/v1/owner/login', [
            'email' => 'owner@example.test',
            'password' => 'wrong-password',
        ])->assertUnauthorized();

        $response = $this->postJson('/api/qr/v1/owner/login', [
            'email' => 'owner@example.test',
            'password' => 'a-long-test-password',
        ])->assertOk()->assertJsonPath('user.role', 'owner');

        $this->withToken($response->json('token'))->getJson('/api/qr/v1/owner/me')
            ->assertOk()->assertJsonPath('user.email', 'owner@example.test');
    }
}
