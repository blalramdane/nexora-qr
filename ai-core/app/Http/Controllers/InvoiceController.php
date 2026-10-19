<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Order;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $restaurant = $request->user()->restaurants()->where('restaurants.is_active', true)->firstOrFail();
        app(TenantContext::class)->set($restaurant);

        $invoices = Invoice::query()
            ->where('restaurant_id', $restaurant->id)
            ->with(['order'])
            ->orderByDesc('issued_at')
            ->paginate(15);

        return response()->json($invoices);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $restaurant = $request->user()->restaurants()->where('restaurants.is_active', true)->firstOrFail();
        app(TenantContext::class)->set($restaurant);

        $invoice = Invoice::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('id', $id)
            ->with(['order.items', 'order.restaurantTable'])
            ->firstOrFail();

        return response()->json($invoice);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'customer_name' => ['nullable', 'string', 'max:120'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $restaurant = $request->user()->restaurants()->where('restaurants.is_active', true)->firstOrFail();
        app(TenantContext::class)->set($restaurant);

        $order = Order::query()
            ->where('id', $data['order_id'])
            ->where('restaurant_id', $restaurant->id)
            ->firstOrFail();

        if ($order->invoice) {
            return response()->json(['message' => 'الفاتورة موجودة بالفعل لهذا الطلب.'], 409);
        }

        $invoice = DB::transaction(function () use ($order, $data, $restaurant) {
            $invoice = Invoice::create([
                'restaurant_id' => $restaurant->id,
                'order_id' => $order->id,
                'invoice_number' => (new Invoice)->generateInvoiceNumber(),
                'customer_name' => $data['customer_name'] ?? $order->customer_name,
                'customer_phone' => $data['customer_phone'] ?? $order->customer_phone,
                'customer_email' => $data['customer_email'],
                'fulfillment_type' => $order->fulfillment_type,
                'payment_method' => $order->payment_method,
                'payment_status' => $order->payment_status,
                'subtotal' => $order->subtotal,
                'delivery_fee' => $order->delivery_fee,
                'discount_amount' => $order->discount_amount,
                'tax_amount' => 0,
                'total' => $order->total,
                'notes' => $data['notes'],
                'status' => 'issued',
                'issued_at' => now(),
            ]);

            $order->update(['invoice_id' => $invoice->id]);

            return $invoice->fresh(['order.items']);
        });

        return response()->json($invoice, 201);
    }

    public function markAsPaid(Request $request, string $id): JsonResponse
    {
        $restaurant = $request->user()->restaurants()->where('restaurants.is_active', true)->firstOrFail();
        app(TenantContext::class)->set($restaurant);

        $invoice = Invoice::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('id', $id)
            ->firstOrFail();

        $invoice->markAsPaid();

        return response()->json(['message' => 'تمت marking الفاتورة كمدفوعة.', 'invoice' => $invoice]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $restaurant = $request->user()->restaurants()->where('restaurants.is_active', true)->firstOrFail();
        app(TenantContext::class)->set($restaurant);

        $invoice = Invoice::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('id', $id)
            ->firstOrFail();

        $invoice->delete();

        return response()->json(['message' => 'تم حذف الفاتورة.']);
    }
}
