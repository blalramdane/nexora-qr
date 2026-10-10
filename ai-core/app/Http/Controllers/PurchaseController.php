<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\Supplier;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeManagement(); $restaurantId = app(TenantContext::class)->id();
        $suppliers = Supplier::query()->where('restaurant_id', $restaurantId)->orderBy('name')->get(['id','name','phone','company_name','tax_number','notes','is_active']);
        $purchases = Purchase::query()->where('restaurant_id', $restaurantId)->with(['supplier:id,name','items:id,purchase_id,item_name,quantity,unit,unit_cost,line_total'])->latest('purchase_date')->latest('id')->limit(100)->get()->map(fn ($p) => [
            'id' => $p->id, 'supplier_id' => $p->supplier_id, 'supplier_name' => $p->supplier?->name ?? 'مورد محذوف',
            'invoice_number' => $p->invoice_number, 'purchase_date' => $p->purchase_date->toDateString(), 'total_amount' => (float) $p->total_amount,
            'paid_amount' => (float) $p->paid_amount, 'remaining_amount' => $p->remaining_amount, 'notes' => $p->notes,
            'items' => $p->items->map(fn ($i) => ['id'=>$i->id,'item_name'=>$i->item_name,'quantity'=>(float)$i->quantity,'unit'=>$i->unit,'unit_cost'=>(float)$i->unit_cost,'line_total'=>(float)$i->line_total])->values(),
        ]);
        return Inertia::render('Finance/Purchases', ['suppliers'=>$suppliers, 'purchases'=>$purchases,
            'summary'=>['purchase_total'=>round((float)Purchase::where('restaurant_id',$restaurantId)->sum('total_amount'),2),'paid_total'=>round((float)Purchase::where('restaurant_id',$restaurantId)->sum('paid_amount'),2),'debt_total'=>round((float)Purchase::where('restaurant_id',$restaurantId)->selectRaw('COALESCE(SUM(total_amount - paid_amount), 0) as debt')->value('debt'),2)],
        ]);
    }

    public function storeSupplier(Request $request): RedirectResponse
    {
        $this->authorizeManagement(); $data = $request->validate(['name'=>['required','string','min:2','max:160'],'phone'=>['nullable','string','max:32'],'company_name'=>['nullable','string','max:160'],'tax_number'=>['nullable','string','max:80'],'notes'=>['nullable','string','max:2000']]);
        Supplier::create(['restaurant_id'=>app(TenantContext::class)->id(), ...$data]);
        return to_route('purchases.index')->with('success','تم إضافة المورد.');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManagement(); $restaurantId = app(TenantContext::class)->id();
        $data = $request->validate([
            'supplier_id'=>['required','integer'], 'invoice_number'=>['nullable','string','max:100'], 'purchase_date'=>['required','date_format:Y-m-d'],
            'paid_amount'=>['required','numeric','min:0','max:999999999.99'], 'notes'=>['nullable','string','max:2000'],
            'items'=>['required','array','min:1','max:100'], 'items.*.item_name'=>['required','string','min:1','max:160'],
            'items.*.quantity'=>['required','numeric','gt:0','max:999999999'], 'items.*.unit'=>['required','string','max:40'],
            'items.*.unit_cost'=>['required','numeric','min:0','max:999999999.999'],
        ]);
        $supplier = Supplier::query()->where('restaurant_id',$restaurantId)->where('is_active',true)->findOrFail($data['supplier_id']);
        $total = round(collect($data['items'])->sum(fn($item)=>(float)$item['quantity']*(float)$item['unit_cost']),2);
        abort_if($total <= 0, 422, 'إجمالي الفاتورة لازم يكون أكبر من صفر.');
        abort_if((float)$data['paid_amount'] > $total, 422, 'المبلغ المدفوع لا يمكن أن يتجاوز إجمالي الفاتورة.');
        DB::transaction(function () use ($data,$restaurantId,$request,$supplier,$total) {
            $purchase = Purchase::create(['restaurant_id'=>$restaurantId,'supplier_id'=>$supplier->id,'created_by'=>$request->user()->id,'invoice_number'=>$data['invoice_number']??null,'purchase_date'=>$data['purchase_date'],'total_amount'=>$total,'paid_amount'=>$data['paid_amount'],'notes'=>$data['notes']??null]);
            foreach ($data['items'] as $item) $purchase->items()->create(['item_name'=>$item['item_name'],'quantity'=>$item['quantity'],'unit'=>$item['unit'],'unit_cost'=>$item['unit_cost'],'line_total'=>round((float)$item['quantity']*(float)$item['unit_cost'],2)]);
        });
        return to_route('purchases.index')->with('success','تم تسجيل فاتورة الشراء وحساب المبلغ المتبقي.');
    }

    public function recordPayment(Request $request, int $purchase): RedirectResponse
    {
        $this->authorizeManagement(); $restaurantId=app(TenantContext::class)->id();
        $data=$request->validate(['amount'=>['required','numeric','gt:0','max:999999999.99']]);
        DB::transaction(function () use ($purchase,$restaurantId,$data) {
            $record=Purchase::query()->where('restaurant_id',$restaurantId)->lockForUpdate()->findOrFail($purchase);
            abort_if((float)$data['amount'] > $record->remaining_amount,422,'المبلغ أكبر من المديونية المتبقية للمورد.');
            $record->increment('paid_amount',round((float)$data['amount'],2));
        });
        return to_route('purchases.index')->with('success','تم تسجيل دفعة المورد وتحديث المديونية.');
    }

    private function authorizeManagement(): void
    {
        $user=request()->user(); $restaurant=app(TenantContext::class)->restaurant();
        abort_unless($user?->isOwnerOf($restaurant)||$user?->roleIn($restaurant)==='manager',403);
    }
}
