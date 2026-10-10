<?php

namespace Tests\Feature;

use App\Models\DeliveryRider;
use App\Models\DeliveryRiderLedgerEntry;
use App\Models\Menu;
use App\Models\Order;
use App\Models\Purchase;
use App\Models\Restaurant;
use App\Models\RestaurantExpense;
use App\Models\Supplier;
use App\Models\Template;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryCashSettlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_record_rider_collection_and_handover_with_calculated_expected_total(): void
    {
        [$restaurant, $user, $rider, $orders] = $this->fixture();
        $this->actingAs($user)->post(route('delivery.settlements.store'), [
            'delivery_rider_id' => $rider->id, 'order_ids' => $orders->pluck('id')->all(),
            'collected_amount' => 150, 'handed_over_amount' => 125, 'notes' => 'تسوية نهاية الوردية',
        ])->assertRedirect(route('delivery.settlements.index'));

        $this->assertDatabaseHas('delivery_cash_settlements', [
            'restaurant_id' => $restaurant->id, 'delivery_rider_id' => $rider->id,
            'expected_amount' => 150, 'collected_amount' => 150, 'handed_over_amount' => 125, 'status' => 'confirmed',
        ]);
        $this->assertDatabaseCount('delivery_cash_settlement_orders', 2);
        $this->assertDatabaseHas('delivery_cash_settlement_orders', ['order_id' => $orders[0]->id]);
        $this->assertSame('paid', $orders[0]->fresh()->payment_status);
    }

    public function test_settlement_rejects_orders_assigned_to_another_rider_and_handover_above_collection(): void
    {
        [, $user, $rider, $orders] = $this->fixture();
        $this->actingAs($user)->post(route('delivery.settlements.store'), [
            'delivery_rider_id' => $rider->id, 'order_ids' => [$orders[0]->id],
            'collected_amount' => 50, 'handed_over_amount' => 60,
        ])->assertUnprocessable();
        $this->assertDatabaseCount('delivery_cash_settlements', 0);

        $otherRider = DeliveryRider::create(['user_id' => User::factory()->create()->id, 'phone' => '01000000001', 'vehicle_type' => 'motorcycle', 'coverage_areas' => ['الزرقا'], 'status' => 'active']);
        $this->actingAs($user)->post(route('delivery.settlements.store'), [
            'delivery_rider_id' => $otherRider->id, 'order_ids' => [$orders[0]->id],
            'collected_amount' => 50, 'handed_over_amount' => 50,
        ])->assertNotFound();
        $this->assertDatabaseCount('delivery_cash_settlements', 0);
    }

    public function test_already_settled_order_cannot_be_added_to_another_settlement(): void
    {
        [, $user, $rider, $orders] = $this->fixture();
        $payload = ['delivery_rider_id' => $rider->id, 'order_ids' => [$orders[0]->id], 'collected_amount' => 75, 'handed_over_amount' => 75];
        $this->actingAs($user)->post(route('delivery.settlements.store'), $payload)->assertRedirect(route('delivery.settlements.index'));
        $this->actingAs($user)->post(route('delivery.settlements.store'), $payload)->assertUnprocessable();
        $this->assertDatabaseCount('delivery_cash_settlements', 1);
    }

    public function test_settlement_screen_is_available_to_restaurant_owner(): void
    {
        [, $user] = $this->fixture();
        $this->actingAs($user)->get(route('delivery.settlements.index'))->assertOk()->assertInertia(fn ($page) => $page
            ->component('Delivery/Settlements')->has('eligibleOrders')->has('settlements')->has('summary'));
    }

    public function test_settlement_also_records_collection_and_handover_in_rider_ledger(): void
    {
        [, $user, $rider, $orders] = $this->fixture();
        $this->actingAs($user)->post(route('delivery.settlements.store'), [
            'delivery_rider_id' => $rider->id, 'order_ids' => [$orders[0]->id],
            'collected_amount' => 75, 'handed_over_amount' => 60,
        ])->assertRedirect(route('delivery.settlements.index'));

        $this->assertDatabaseHas('delivery_rider_ledger_entries', ['delivery_rider_id' => $rider->id, 'type' => 'collection', 'amount' => 75]);
        $this->assertDatabaseHas('delivery_rider_ledger_entries', ['delivery_rider_id' => $rider->id, 'type' => 'handover', 'amount' => 60]);
    }

    public function test_owner_can_record_expense_in_rider_ledger_and_opening_balance_is_only_allowed_once(): void
    {
        [, $user, $rider] = $this->fixture();
        $this->actingAs($user)->post(route('delivery.ledger.store'), [
            'delivery_rider_id' => $rider->id, 'type' => 'expense', 'amount' => 45,
            'description' => 'بنزين توصيلات اليوم', 'reference' => 'REC-001',
        ])->assertRedirect(route('delivery.ledger.index', ['rider_id' => $rider->id]));
        $this->assertDatabaseHas('delivery_rider_ledger_entries', ['restaurant_id' => app(TenantContext::class)->id(), 'delivery_rider_id' => $rider->id, 'type' => 'expense', 'amount' => 45]);

        $opening = ['delivery_rider_id' => $rider->id, 'type' => 'opening_balance', 'amount' => 100, 'description' => 'رصيد بداية التعامل'];
        $this->actingAs($user)->post(route('delivery.ledger.store'), $opening)->assertRedirect();
        $this->actingAs($user)->post(route('delivery.ledger.store'), $opening)->assertUnprocessable();
        $this->assertDatabaseCount('delivery_rider_ledger_entries', 2);
    }

    public function test_ledger_screen_is_available_to_restaurant_owner(): void
    {
        [, $user] = $this->fixture();
        $this->actingAs($user)->get(route('delivery.ledger.index'))->assertOk()->assertInertia(fn ($page) => $page
            ->component('Delivery/Ledger')->has('riders')->has('entries')->has('summary'));
    }

    public function test_owner_can_close_rider_shift_with_expected_cash_and_differences(): void
    {
        [$restaurant, $user, $rider] = $this->fixture();
        $this->actingAs($user)->post(route('delivery.shift-closings.store'), [
            'delivery_rider_id' => $rider->id, 'shift_date' => now()->toDateString(), 'shift_name' => 'مسائي',
            'collected_amount' => 140, 'handed_over_amount' => 120, 'notes' => 'عجز 10 جنيه وتأخير تسليم 20 جنيه',
        ])->assertRedirect(route('delivery.shift-closings.index', ['rider_id' => $rider->id, 'date' => now()->toDateString()]));

        $this->assertDatabaseHas('delivery_shift_closings', [
            'restaurant_id' => $restaurant->id, 'delivery_rider_id' => $rider->id,
            'expected_amount' => 150, 'collected_amount' => 140, 'handed_over_amount' => 120,
            'collection_difference' => 10, 'handover_difference' => 20, 'shift_name' => 'مسائي',
        ]);
        $this->assertDatabaseCount('delivery_rider_ledger_entries', 0);
    }

    public function test_shift_closing_requires_reason_for_difference_and_cannot_be_duplicated(): void
    {
        [, $user, $rider] = $this->fixture();
        $payload = ['delivery_rider_id' => $rider->id, 'shift_date' => now()->toDateString(), 'shift_name' => 'مسائي', 'collected_amount' => 150, 'handed_over_amount' => 140];
        $this->actingAs($user)->post(route('delivery.shift-closings.store'), $payload)->assertUnprocessable();
        $this->assertDatabaseCount('delivery_shift_closings', 0);

        $payload['handed_over_amount'] = 150;
        $this->actingAs($user)->post(route('delivery.shift-closings.store'), $payload)->assertRedirect();
        $this->actingAs($user)->post(route('delivery.shift-closings.store'), $payload)->assertRedirect();
        $this->assertDatabaseCount('delivery_shift_closings', 1);
    }

    public function test_shift_closing_screen_is_available_to_restaurant_owner(): void
    {
        [, $user] = $this->fixture();
        $this->actingAs($user)->get(route('delivery.shift-closings.index'))->assertOk()->assertInertia(fn ($page) => $page
            ->component('Delivery/ShiftClosings')->has('riders')->has('orders')->has('summary')->has('closings'));
    }

    public function test_daily_financial_report_calculates_sales_payment_status_and_rider_cash_separately(): void
    {
        [$restaurant, $user, $rider] = $this->fixture();
        DeliveryRiderLedgerEntry::create(['restaurant_id' => $restaurant->id, 'delivery_rider_id' => $rider->id,
            'created_by' => $user->id, 'type' => 'collection', 'amount' => 100, 'description' => 'تحصيل تجريبي', 'occurred_at' => now()]);
        DeliveryRiderLedgerEntry::create(['restaurant_id' => $restaurant->id, 'delivery_rider_id' => $rider->id,
            'created_by' => $user->id, 'type' => 'handover', 'amount' => 80, 'description' => 'تسليم تجريبي', 'occurred_at' => now()]);

        $this->actingAs($user)->get(route('reports.financial.index', ['date' => now()->toDateString()]))
            ->assertOk()->assertInertia(fn ($page) => $page->component('Finance/DailyReport')
                ->where('date', now()->toDateString())
                ->where('metrics.0.value', 2)
                ->where('metrics.1.value', 150)
                ->where('metrics.3.value', 150)
                ->where('metrics.9.value', 80)
                ->where('metrics.10.value', 20)
                ->has('payments', 5)->has('riders', 1));
    }

    public function test_daily_financial_report_can_be_exported_as_csv(): void
    {
        [, $user] = $this->fixture();
        $this->actingAs($user)->get(route('reports.financial.export', ['date' => now()->toDateString()]))
            ->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('content-disposition', 'attachment; filename=nexora-daily-finance-'.now()->toDateString().'.csv');
    }

    public function test_owner_can_record_restaurant_expense_and_report_groups_it_by_category(): void
    {
        [$restaurant, $user] = $this->fixture();
        $this->actingAs($user)->post(route('expenses.store'), [
            'category' => 'utilities', 'title' => 'فاتورة كهرباء', 'amount' => 325.50,
            'payment_method' => 'cash', 'expense_date' => now()->toDateString(), 'reference' => 'ELEC-01',
        ])->assertRedirect(route('expenses.index', ['date' => now()->toDateString()]));

        $this->assertDatabaseHas('restaurant_expenses', ['restaurant_id' => $restaurant->id, 'category' => 'utilities', 'amount' => 325.50]);
        $this->actingAs($user)->get(route('expenses.index', ['date' => now()->toDateString()]))
            ->assertOk()->assertInertia(fn ($page) => $page->component('Finance/Expenses')->where('total', 325.5)->has('expenses', 1));
        $this->actingAs($user)->get(route('reports.financial.index', ['date' => now()->toDateString()]))
            ->assertOk()->assertInertia(fn ($page) => $page->where('metrics.16.value', 325.5)->where('expenseCategories.0.category', 'utilities'));
    }

    public function test_restaurant_expense_cannot_be_deleted_across_tenants(): void
    {
        [$restaurant, $user] = $this->fixture();
        $expense = RestaurantExpense::create(['restaurant_id' => $restaurant->id, 'created_by' => $user->id, 'category' => 'other', 'title' => 'اختبار', 'amount' => 10, 'payment_method' => 'cash', 'expense_date' => now()->toDateString()]);
        $other = Restaurant::create(['name' => 'Other Restaurant', 'slug' => 'other-restaurant', 'is_active' => true]);
        app(TenantContext::class)->set($other);
        $otherUser = User::factory()->create();
        $otherUser->restaurants()->attach($other->id, ['role' => 'owner']);
        $this->actingAs($otherUser)->delete(route('expenses.destroy', $expense->id))->assertNotFound();
        $this->assertDatabaseHas('restaurant_expenses', ['id' => $expense->id, 'restaurant_id' => $restaurant->id]);
    }

    public function test_owner_can_record_purchase_and_supplier_payment_without_overpaying(): void
    {
        [$restaurant, $user] = $this->fixture();
        $this->actingAs($user)->post(route('suppliers.store'), ['name'=>'مورد الخضار','phone'=>'01012345678'])
            ->assertRedirect(route('purchases.index'));
        $supplier = Supplier::where('restaurant_id',$restaurant->id)->firstOrFail();
        $this->actingAs($user)->post(route('purchases.store'), [
            'supplier_id'=>$supplier->id,'invoice_number'=>'INV-001','purchase_date'=>now()->toDateString(),'paid_amount'=>100,'notes'=>'توريد تجريبي',
            'items'=>[['item_name'=>'طماطم','quantity'=>10,'unit'=>'كيلو','unit_cost'=>25],['item_name'=>'بطاطس','quantity'=>5,'unit'=>'كيلو','unit_cost'=>30]],
        ])->assertRedirect(route('purchases.index'));
        $purchase=Purchase::where('restaurant_id',$restaurant->id)->firstOrFail();
        $this->assertSame(400.0,(float)$purchase->total_amount);
        $this->assertSame(100.0,(float)$purchase->paid_amount);
        $this->assertSame(300.0,$purchase->remaining_amount);
        $this->actingAs($user)->post(route('purchases.payments.store',$purchase),['amount'=>150])->assertRedirect(route('purchases.index'));
        $this->assertSame(250.0,(float)$purchase->fresh()->paid_amount);
        $this->actingAs($user)->post(route('purchases.payments.store',$purchase),['amount'=>151])->assertUnprocessable();
        $this->assertSame(250.0,(float)$purchase->fresh()->paid_amount);
        $this->actingAs($user)->get(route('purchases.index'))->assertOk()->assertInertia(fn($page)=>$page->component('Finance/Purchases')->has('suppliers',1)->has('purchases',1)->where('summary.debt_total',150));
    }

    private function fixture(): array
    {
        $restaurant = Restaurant::create(['name' => 'Settlement Restaurant', 'slug' => 'settlement-restaurant', 'is_active' => true]);
        app(TenantContext::class)->set($restaurant);
        $user = User::factory()->create();
        $user->restaurants()->attach($restaurant->id, ['role' => 'owner']);
        $riderUser = User::factory()->create();
        $rider = DeliveryRider::create(['user_id' => $riderUser->id, 'phone' => '01000000000', 'vehicle_type' => 'motorcycle', 'coverage_areas' => ['الزرقا'], 'status' => 'active']);
        $restaurant->deliveryRiders()->attach($rider->id, ['status' => 'active', 'invited_by' => $user->id]);
        $template = Template::create(['key' => 'settlement-template', 'name' => 'Settlement', 'is_active' => true]);
        $version = $template->createVersion(['layout' => []], true);
        $menu = Menu::create(['restaurant_id' => $restaurant->id, 'template_id' => $template->id, 'template_version_id' => $version->id, 'name' => 'Menu', 'slug' => 'settlement-menu', 'is_published' => true]);
        $orders = collect([75, 75])->map(fn ($total, $index) => Order::create([
            'restaurant_id' => $restaurant->id, 'menu_id' => $menu->id, 'order_number' => 'NX-SETTLE-00'.($index + 1),
            'customer_name' => 'Customer '.$index, 'customer_phone' => '01000000000', 'fulfillment_type' => 'delivery',
            'delivery_rider_id' => $rider->id, 'delivery_status' => 'delivered', 'delivered_at' => now(),
            'payment_method' => 'cash_on_delivery', 'payment_status' => 'unpaid', 'status' => 'completed',
            'items_count' => 1, 'subtotal' => $total, 'delivery_fee' => 0, 'total' => $total,
        ]));
        return [$restaurant, $user, $rider, $orders];
    }
}
