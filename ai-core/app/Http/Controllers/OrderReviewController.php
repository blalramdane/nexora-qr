<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderReview;
use App\Models\Restaurant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $restaurant = $request->user()->restaurants()->where('restaurants.is_active', true)->firstOrFail();
        app(TenantContext::class)->set($restaurant);

        $reviews = OrderReview::query()
            ->where('restaurant_id', $restaurant->id)
            ->with(['customer', 'order'])
            ->orderByDesc('created_at')
            ->paginate(15);

        return response()->json($reviews);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'rating_breakdown' => ['nullable', 'array'],
        ]);

        $restaurant = $request->user()->restaurants()->where('restaurants.is_active', true)->firstOrFail();
        app(TenantContext::class)->set($restaurant);

        $order = Order::query()
            ->where('id', $data['order_id'])
            ->where('restaurant_id', $restaurant->id)
            ->where('status', 'delivered')
            ->firstOrFail();

        if ($order->review) {
            return response()->json(['message' => 'تم تقييم هذا الطلب مسبقاً.'], 409);
        }

        $review = OrderReview::create([
            'restaurant_id' => $restaurant->id,
            'order_id' => $order->id,
            'customer_id' => $request->user()->id,
            'menu_id' => $order->menu_id,
            'rating' => $data['rating'],
            'comment' => $data['comment'],
            'rating_breakdown' => $data['rating_breakdown'] ?? null,
            'is_approved' => false,
            'is_published' => false,
        ]);

        return response()->json($review, 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $restaurant = $request->user()->restaurants()->where('restaurants.is_active', true)->firstOrFail();
        app(TenantContext::class)->set($restaurant);

        $review = OrderReview::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('id', $id)
            ->firstOrFail();

        $data = $request->validate([
            'is_approved' => ['sometimes', 'boolean'],
            'is_published' => ['sometimes', 'boolean'],
        ]);

        $review->update($data);

        if ($data['is_published'] ?? false) {
            $review->update(['published_at' => now()]);
        }

        return response()->json($review);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $restaurant = $request->user()->restaurants()->where('restaurants.is_active', true)->firstOrFail();
        app(TenantContext::class)->set($restaurant);

        $review = OrderReview::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('id', $id)
            ->firstOrFail();

        $review->delete();

        return response()->json(['message' => 'تم حذف التقييم.']);
    }

    public function public(Request $request, string $restaurantSlug): JsonResponse
    {
        $restaurant = Restaurant::query()->where('slug', $restaurantSlug)->where('is_active', true)->firstOrFail();

        $reviews = OrderReview::query()
            ->where('restaurant_id', $restaurant->id)
            ->published()
            ->with('customer')
            ->orderByDesc('published_at')
            ->limit(20)
            ->get();

        $averageRating = OrderReview::published()
            ->where('restaurant_id', $restaurant->id)
            ->avg('rating') ?? 0.0;

        return response()->json([
            'restaurant' => ['name' => $restaurant->name, 'slug' => $restaurant->slug],
            'average_rating' => round((float) $averageRating, 1),
            'total_reviews' => $reviews->count(),
            'reviews' => $reviews,
        ]);
    }
}
