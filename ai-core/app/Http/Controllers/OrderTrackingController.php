<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Restaurant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OrderTrackingController extends Controller
{
    public function show(string $restaurantSlug, string $orderNumber, string $token): Response
    {
        $restaurant = Restaurant::query()
            ->where('slug', $restaurantSlug)
            ->where('is_active', true)
            ->firstOrFail();

        $order = Order::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('order_number', $orderNumber)
            ->where('tracking_token_hash', hash('sha256', $token))
            ->with(['items', 'statusHistory', 'deliveryRider.user:id,name', 'review'])
            ->firstOrFail();

        return Inertia::render('Orders/Track', [
            'restaurant' => ['name' => $restaurant->name, 'slug' => $restaurant->slug],
            'order' => [
                'number' => $order->order_number,
                'status' => $order->status,
                'customerName' => $order->customer_name,
                'fulfillmentType' => $order->fulfillment_type,
                'deliveryAddress' => $order->delivery_address,
                'deliveryArea' => $order->delivery_area,
                'deliveryStatus' => $order->delivery_status,
                'deliveryRiderName' => $order->deliveryRider?->user?->name,
                'deliveryPickedUpAt' => $order->delivery_picked_up_at,
                'deliveredAt' => $order->delivered_at,
                'subtotal' => $order->subtotal,
                'discountAmount' => $order->discount_amount,
                'couponCode' => $order->coupon_code,
                'deliveryFee' => $order->delivery_fee,
                'total' => $order->total,
                'createdAt' => $order->created_at,
                'history' => $order->statusHistory->map(fn ($event) => [
                    'status' => $event->to_status,
                    'createdAt' => $event->created_at,
                ])->values(),
                'review' => $order->review ? ['rating' => $order->review->rating, 'comment' => $order->review->comment, 'createdAt' => $order->review->created_at] : null,
                'items' => $order->items->map(fn ($item) => [
                    'name' => $item->product_name,
                    'variant' => $item->variant_name,
                    'modifiers' => $item->modifiers ?? [],
                    'quantity' => $item->quantity,
                    'total' => $item->line_total,
                ])->values(),
            ],
        ]);
    }

    public function storeReview(Request $request, string $restaurantSlug, string $orderNumber, string $token): RedirectResponse
    {
        $restaurant = Restaurant::query()->where('slug', $restaurantSlug)->where('is_active', true)->firstOrFail();
        $order = Order::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('order_number', $orderNumber)
            ->where('tracking_token_hash', hash('sha256', $token))
            ->firstOrFail();

        if ($order->status !== 'completed') {
            throw ValidationException::withMessages(['rating' => 'يمكنك تقييم الطلب بعد اكتماله فقط.']);
        }
        if ($order->review()->exists()) {
            throw ValidationException::withMessages(['rating' => 'تم تسجيل تقييم لهذا الطلب بالفعل.']);
        }

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $order->review()->create([
            'restaurant_id' => $restaurant->id,
            'rating' => $data['rating'],
            'comment' => isset($data['comment']) ? trim($data['comment']) ?: null : null,
        ]);

        return back()->with('success', 'شكرًا ليك! تم تسجيل تقييمك.');
    }
}
