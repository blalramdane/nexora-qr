<?php

namespace App\Http\Controllers;

use App\Models\DeliveryCashSettlement;
use App\Models\DeliveryRiderLedgerEntry;
use App\Models\DeliveryShiftClosing;
use App\Models\Order;
use App\Models\Purchase;
use App\Models\RestaurantExpense;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class FinancialReportController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $this->authorizeManagement();
        $date = $this->reportDate($request);
        return Inertia::render('Finance/DailyReport', $this->reportData($date));
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorizeManagement();
        $data = $this->reportData($this->reportDate($request));
        $filename = 'nexora-daily-finance-' . $data['date'] . '.csv';
        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['تقرير نكسورا المالي اليومي', $data['date']]);
            fputcsv($out, ['البند', 'القيمة بالجنيه المصري']);
            foreach ($data['metrics'] as $metric) fputcsv($out, [$metric['label'], number_format((float) $metric['value'], 2, '.', '')]);
            fputcsv($out, []);
            fputcsv($out, ['طرق الدفع', 'عدد الطلبات', 'إجمالي المبيعات', 'المدفوع', 'غير المدفوع', 'المسترد']);
            foreach ($data['payments'] as $row) fputcsv($out, [$row['label'], $row['orders'], $row['sales'], $row['paid'], $row['unpaid'], $row['refunded']]);
            fputcsv($out, []);
            fputcsv($out, ['المندوب', 'طلبات نقدية مسلّمة', 'المتوقع', 'التحصيل المسجل', 'المسلّم للمطعم', 'مصروفات المندوب', 'فرق التحصيل', 'نقدية غير مسلّمة']);
            foreach ($data['riders'] as $row) fputcsv($out, [$row['name'], $row['orders_count'], $row['expected'], $row['collected'], $row['handed_over'], $row['expenses'], $row['collection_difference'], $row['pending_handover']]);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function reportData(string $date): array
    {
        $restaurantId = app(TenantContext::class)->id();
        $orders = Order::query()->where('restaurant_id', $restaurantId)->whereDate('created_at', $date);
        $sales = (float) (clone $orders)->sum('total');
        $discounts = (float) (clone $orders)->sum('discount_amount');
        $deliveryFees = (float) (clone $orders)->sum('delivery_fee');
        $paid = (float) (clone $orders)->where('payment_status', 'paid')->sum('total');
        $unpaid = (float) (clone $orders)->where('payment_status', 'unpaid')->sum('total');
        $refunded = (float) (clone $orders)->where('payment_status', 'refunded')->sum('total');
        $orderCount = (clone $orders)->count();

        $methodLabels = ['cash_on_delivery' => 'نقدًا عند الاستلام', 'cash' => 'نقدي', 'card' => 'بطاقة', 'wallet' => 'محفظة إلكترونية', 'bank_transfer' => 'تحويل بنكي'];
        $payments = collect($methodLabels)->map(function (string $label, string $method) use ($orders) {
            $query = (clone $orders)->where('payment_method', $method);
            return ['method' => $method, 'label' => $label, 'orders' => (clone $query)->count(), 'sales' => round((float) (clone $query)->sum('total'), 2),
                'paid' => round((float) (clone $query)->where('payment_status', 'paid')->sum('total'), 2),
                'unpaid' => round((float) (clone $query)->where('payment_status', 'unpaid')->sum('total'), 2),
                'refunded' => round((float) (clone $query)->where('payment_status', 'refunded')->sum('total'), 2)];
        })->values();

        $deliveredCashOrders = Order::query()->where('restaurant_id', $restaurantId)->where('fulfillment_type', 'delivery')
            ->where('delivery_status', 'delivered')->whereIn('payment_method', ['cash_on_delivery', 'cash'])->whereDate('delivered_at', $date)->get(['id', 'delivery_rider_id', 'total']);
        $ledger = DeliveryRiderLedgerEntry::query()->where('restaurant_id', $restaurantId)->whereDate('occurred_at', $date)->get();
        $riderIds = $deliveredCashOrders->pluck('delivery_rider_id')->filter()->merge($ledger->pluck('delivery_rider_id'))->unique()->values();
        $riderModels = \App\Models\DeliveryRider::query()->whereIn('id', $riderIds)->with('user:id,name')->get()->keyBy('id');
        $riders = $riderIds->map(function ($riderId) use ($deliveredCashOrders, $ledger, $riderModels) {
            $riderOrders = $deliveredCashOrders->where('delivery_rider_id', $riderId);
            $entries = $ledger->where('delivery_rider_id', $riderId);
            $collected = (float) $entries->where('type', 'collection')->sum('amount');
            $handedOver = (float) $entries->where('type', 'handover')->sum('amount');
            $expected = (float) $riderOrders->sum('total');
            return ['rider_id' => $riderId, 'name' => $riderModels->get($riderId)?->user?->name ?? 'مندوب غير متاح',
                'orders_count' => $riderOrders->count(), 'expected' => round($expected, 2), 'collected' => round($collected, 2),
                'handed_over' => round($handedOver, 2), 'expenses' => round((float) $entries->where('type', 'expense')->sum('amount'), 2),
                'collection_difference' => round($expected - $collected, 2), 'pending_handover' => round($collected - $handedOver, 2)];
        })->values();

        $settlements = DeliveryCashSettlement::query()->where('restaurant_id', $restaurantId)->whereDate('created_at', $date)->get();
        $closings = DeliveryShiftClosing::query()->where('restaurant_id', $restaurantId)->whereDate('shift_date', $date)->get();
        $restaurantExpenses = RestaurantExpense::query()->where('restaurant_id', $restaurantId)->whereDate('expense_date', $date)->get();
        $expenseTotal = round((float) $restaurantExpenses->sum('amount'), 2);
        $purchaseDay = Purchase::query()->where('restaurant_id', $restaurantId)->whereDate('purchase_date', $date);
        $purchaseTotal = round((float) (clone $purchaseDay)->sum('total_amount'), 2);
        $purchasePaid = round((float) (clone $purchaseDay)->sum('paid_amount'), 2);
        $supplierDebt = round((float) Purchase::query()->where('restaurant_id', $restaurantId)->selectRaw('COALESCE(SUM(total_amount - paid_amount), 0) as debt')->value('debt'), 2);
        $ledgerExpenses = round((float) $ledger->where('type', 'expense')->sum('amount'), 2);
        $handoverTotal = round((float) $ledger->where('type', 'handover')->sum('amount'), 2);
        $collectionTotal = round((float) $ledger->where('type', 'collection')->sum('amount'), 2);

        return ['date' => $date, 'restaurant' => app(TenantContext::class)->restaurant()->only(['id', 'name']),
            'metrics' => [
                ['key' => 'orders', 'label' => 'عدد الطلبات', 'value' => $orderCount],
                ['key' => 'sales', 'label' => 'إجمالي قيمة الطلبات', 'value' => round($sales, 2)],
                ['key' => 'paid', 'label' => 'قيمة الطلبات المدفوعة', 'value' => round($paid, 2)],
                ['key' => 'unpaid', 'label' => 'المبالغ غير المحصلة حسب حالة الدفع', 'value' => round($unpaid, 2)],
                ['key' => 'refunded', 'label' => 'قيمة الطلبات المستردة', 'value' => round($refunded, 2)],
                ['key' => 'discounts', 'label' => 'إجمالي الخصومات', 'value' => round($discounts, 2)],
                ['key' => 'delivery_fees', 'label' => 'مصاريف التوصيل المسجلة على الطلبات', 'value' => round($deliveryFees, 2)],
                ['key' => 'delivered_cash_expected', 'label' => 'المتوقع من طلبات التوصيل النقدية المسلّمة', 'value' => round((float) $deliveredCashOrders->sum('total'), 2)],
                ['key' => 'rider_collections', 'label' => 'تحصيلات المندوبين المسجلة اليوم', 'value' => $collectionTotal],
                ['key' => 'rider_handovers', 'label' => 'ما سلّمه المندوبون للمطعم اليوم', 'value' => $handoverTotal],
                ['key' => 'rider_pending_handover', 'label' => 'تحصيل مسجل لم يسلّم بعد', 'value' => round($collectionTotal - $handoverTotal, 2)],
                ['key' => 'rider_expenses', 'label' => 'مصروفات المندوبين المسجلة اليوم', 'value' => $ledgerExpenses],
                ['key' => 'settlements', 'label' => 'عدد التسويات المنشأة اليوم', 'value' => $settlements->count()],
                ['key' => 'closed_shifts', 'label' => 'عدد الورديات المقفولة اليوم', 'value' => $closings->count()],
                ['key' => 'closing_collection_difference', 'label' => 'صافي فروق التحصيل في إقفالات اليوم', 'value' => round((float) $closings->sum('collection_difference'), 2)],
                ['key' => 'closing_handover_difference', 'label' => 'صافي فروق التسليم في إقفالات اليوم', 'value' => round((float) $closings->sum('handover_difference'), 2)],
                ['key' => 'restaurant_expenses', 'label' => 'مصروفات المطعم المسجلة اليوم', 'value' => $expenseTotal],
                ['key' => 'combined_expenses', 'label' => 'مصروفات المطعم والمندوبين المسجلة', 'value' => round($expenseTotal + $ledgerExpenses, 2)],
                ['key' => 'purchases_today', 'label' => 'إجمالي فواتير الشراء بتاريخ اليوم', 'value' => $purchaseTotal],
                ['key' => 'purchase_payments_today', 'label' => 'المدفوع على فواتير شراء اليوم', 'value' => $purchasePaid],
                ['key' => 'supplier_debt', 'label' => 'إجمالي المديونية المتبقية للموردين', 'value' => $supplierDebt],
            ], 'payments' => $payments, 'riders' => $riders,
            'expenseCategories' => $restaurantExpenses->groupBy('category')->map(fn ($rows, $category) => ['category' => $category, 'label' => ['rent'=>'إيجار','utilities'=>'كهرباء ومياه وغاز','supplies'=>'مستلزمات تشغيل','maintenance'=>'صيانة','salaries'=>'رواتب وأجور','marketing'=>'تسويق وإعلانات','transport'=>'نقل ومواصلات','hospitality'=>'ضيافة','other'=>'أخرى'][$category] ?? $category, 'amount' => round((float) $rows->sum('amount'), 2), 'count' => $rows->count()])->values(),
            'notes' => ['المبيعات حسب تاريخ إنشاء الطلب، بينما تحصيلات التوصيل ومصروفات المندوب حسب وقت تسجيل الحركة. لا تجمع مبيعات الطلبات المدفوعة مع تسليمات المندوبين باعتبارهما نقدية منفصلة حتى لا يتكرر احتساب نفس المبلغ.', 'المشتريات تظهر منفصلة عن المصروفات؛ شراء الخامات لا يعني استهلاكها بالكامل في نفس اليوم. التقرير متابعة تشغيلية وليس قائمة دخل أو صافي ربح محاسبي.'],
        ];
    }

    private function reportDate(Request $request): string
    {
        $value = $request->query('date', now()->toDateString());
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) || ! Carbon::canBeCreatedFromFormat($value, 'Y-m-d')) return now()->toDateString();
        return $value;
    }

    private function authorizeManagement(): void
    {
        $user = request()->user();
        $restaurant = app(TenantContext::class)->restaurant();
        abort_unless($user?->isOwnerOf($restaurant) || $user?->roleIn($restaurant) === 'manager', 403);
    }
}
