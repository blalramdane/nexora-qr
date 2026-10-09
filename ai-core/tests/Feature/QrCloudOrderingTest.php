<?php

namespace Tests\Feature;

use App\Models\QrBranch;
use App\Models\QrMenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class QrCloudOrderingTest extends TestCase
{
    use RefreshDatabase;

    private function branch(string $slug = 'branch-a'): QrBranch
    {
        return QrBranch::query()->create([
            'name' => 'Branch '.strtoupper($slug),
            'slug' => $slug,
            'code' => strtoupper(str_replace('-', '', $slug)),
            'menu_version' => 1,
        ]);
    }

    private function publishItem(QrBranch $branch, string $id = 'sku-1', int $price = 12500): QrMenuItem
    {
        return $branch->menuItems()->create([
            'source_product_id' => $id,
            'name' => 'Test Meal',
            'category_name' => 'Meals',
            'price_minor' => $price,
            'currency' => 'EGP',
            'is_available' => true,
        ]);
    }

    public function test_public_menu_exposes_only_active_branch_catalog(): void
    {
        $branch = $this->branch();
        $this->publishItem($branch);
        $other = $this->branch('branch-b');
        $this->publishItem($other, 'sku-b');

        $this->getJson('/api/qr/v1/menus/branch-a')
            ->assertOk()
            ->assertJsonPath('branch.slug', 'branch-a')
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.source_product_id', 'sku-1');
    }

    public function test_customer_total_is_calculated_from_published_catalog(): void
    {
        $branch = $this->branch();
        $this->publishItem($branch, 'sku-1', 12500);

        $payload = [
            'source_order_uuid' => (string) Str::uuid(),
            'menu_version' => 1,
            'fulfillment_type' => 'takeaway',
            'items' => [['source_product_id' => 'sku-1', 'quantity' => 2]],
        ];

        $this->postJson('/api/qr/v1/menus/branch-a/orders', $payload)
            ->assertStatus(202)
            ->assertJsonPath('subtotal_minor', 25000)
            ->assertJsonPath('status', 'pending_delivery');
    }

    public function test_same_request_uuid_is_idempotent_and_payload_change_conflicts(): void
    {
        $branch = $this->branch();
        $this->publishItem($branch);
        $uuid = (string) Str::uuid();
        $payload = [
            'source_order_uuid' => $uuid,
            'menu_version' => 1,
            'fulfillment_type' => 'takeaway',
            'items' => [['source_product_id' => 'sku-1', 'quantity' => 1]],
        ];

        $first = $this->postJson('/api/qr/v1/menus/branch-a/orders', $payload)->assertStatus(202);
        $this->postJson('/api/qr/v1/menus/branch-a/orders', $payload)->assertOk()->assertJsonPath('order_id', $first->json('order_id'));
        $this->postJson('/api/qr/v1/menus/branch-a/orders', array_merge($payload, [
            'items' => [['source_product_id' => 'sku-1', 'quantity' => 2]],
        ]))->assertStatus(409);
        $this->assertDatabaseCount('qr_orders', 1);
    }

    public function test_stale_menu_version_and_unavailable_products_are_rejected(): void
    {
        $branch = $this->branch();
        $this->publishItem($branch);
        $payload = [
            'source_order_uuid' => (string) Str::uuid(),
            'menu_version' => 99,
            'fulfillment_type' => 'takeaway',
            'items' => [['source_product_id' => 'sku-1', 'quantity' => 1]],
        ];
        $this->postJson('/api/qr/v1/menus/branch-a/orders', $payload)->assertStatus(409);
        $payload['menu_version'] = 1;
        $payload['items'][0]['source_product_id'] = 'missing-sku';
        $this->postJson('/api/qr/v1/menus/branch-a/orders', $payload)->assertStatus(422);
        $this->assertDatabaseCount('qr_orders', 0);
    }

    public function test_branch_agent_can_only_receive_orders_for_its_own_branch(): void
    {
        $branchA = $this->branch('branch-a');
        $branchB = $this->branch('branch-b');
        $this->publishItem($branchA, 'a');
        $this->publishItem($branchB, 'b');
        $token = Str::random(64);
        $branchA->agents()->create(['name' => 'A POS', 'token_hash' => hash('sha256', $token)]);

        $payload = [
            'source_order_uuid' => (string) Str::uuid(),
            'menu_version' => 1,
            'fulfillment_type' => 'takeaway',
            'items' => [['source_product_id' => 'a', 'quantity' => 1]],
        ];
        $this->postJson('/api/qr/v1/menus/branch-a/orders', $payload)->assertStatus(202);

        $this->getJson('/api/qr/v1/agent/orders/pending')->assertUnauthorized();
        $this->withToken($token)->getJson('/api/qr/v1/agent/orders/pending')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.branch_slug', 'branch-a');
        $this->withToken(Str::random(64))->getJson('/api/qr/v1/agent/orders/pending')->assertUnauthorized();
    }

    public function test_agent_acknowledgement_requires_current_lease(): void
    {
        $branch = $this->branch();
        $this->publishItem($branch);
        $token = Str::random(64);
        $branch->agents()->create(['name' => 'POS', 'token_hash' => hash('sha256', $token)]);
        $this->postJson('/api/qr/v1/menus/branch-a/orders', [
            'source_order_uuid' => (string) Str::uuid(),
            'menu_version' => 1,
            'fulfillment_type' => 'takeaway',
            'items' => [['source_product_id' => 'sku-1', 'quantity' => 1]],
        ])->assertStatus(202);

        $order = $this->withToken($token)->getJson('/api/qr/v1/agent/orders/pending')->assertOk()->json('data.0');
        $this->withToken($token)->postJson('/api/qr/v1/agent/orders/'.$order['id'].'/acknowledge', [
            'delivery_lease_token' => (string) Str::uuid(),
            'status' => 'imported',
            'local_order_id' => 'local-100',
        ])->assertStatus(409);

        $this->withToken($token)->postJson('/api/qr/v1/agent/orders/'.$order['id'].'/acknowledge', [
            'delivery_lease_token' => $order['delivery_lease_token'],
            'status' => 'imported',
            'local_order_id' => 'local-100',
        ])->assertOk()->assertJsonPath('data.status', 'imported');
    }
    public function test_order_rejects_unvalidated_option_selections(): void
    {
        $branch = $this->branch();
        $this->publishItem($branch);
        $this->postJson('/api/qr/v1/menus/branch-a/orders', [
            'source_order_uuid' => (string) Str::uuid(),
            'menu_version' => 1,
            'fulfillment_type' => 'takeaway',
            'items' => [['source_product_id' => 'sku-1', 'quantity' => 1, 'options' => ['extra-cheese']]],
        ])->assertStatus(422);

        $this->assertDatabaseCount('qr_orders', 0);
    }

    public function test_branch_agent_publishes_first_menu_version(): void
    {
        $branch = QrBranch::query()->create([
            'name' => 'Fresh Branch',
            'slug' => 'fresh-branch',
            'code' => 'FRESH1',
        ]);
        $token = Str::random(64);
        $branch->agents()->create(['name' => 'Primary POS', 'token_hash' => hash('sha256', $token)]);

        $this->withToken($token)->putJson('/api/qr/v1/agent/menu', [
            'menu_version' => 1,
            'items' => [[
                'source_product_id' => 'pos-product-1',
                'name' => 'Meal',
                'price_minor' => 12500,
                'is_available' => true,
            ]],
        ])->assertOk()->assertJsonPath('menu_version', 1);

        $this->assertDatabaseHas('qr_menu_items', [
            'branch_id' => $branch->id,
            'source_product_id' => 'pos-product-1',
            'price_minor' => 12500,
        ]);
    }

}
