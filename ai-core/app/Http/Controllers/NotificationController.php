<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $restaurant = $request->user()->restaurants()->where('restaurants.is_active', true)->firstOrFail();
        app(TenantContext::class)->set($restaurant);

        $notifications = Notification::query()
            ->where('user_id', $request->user()->id)
            ->orWhere('restaurant_id', $restaurant->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($notifications);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $restaurant = $request->user()->restaurants()->where('restaurants.is_active', true)->firstOrFail();
        app(TenantContext::class)->set($restaurant);

        $count = Notification::query()
            ->where(function ($query) use ($request, $restaurant) {
                $query->where('user_id', $request->user()->id)
                    ->orWhere('restaurant_id', $restaurant->id);
            })
            ->where('is_read', false)
            ->count();

        return response()->json(['unread_count' => $count]);
    }

    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $restaurant = $request->user()->restaurants()->where('restaurants.is_active', true)->firstOrFail();
        app(TenantContext::class)->set($restaurant);

        $notification = Notification::query()
            ->where(function ($query) use ($request, $restaurant) {
                $query->where('user_id', $request->user()->id)
                    ->orWhere('restaurant_id', $restaurant->id);
            })
            ->where('id', $id)
            ->firstOrFail();

        $notification->markAsRead();

        return response()->json(['message' => 'تم تحديد الإشعار كمقروء.']);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $restaurant = $request->user()->restaurants()->where('restaurants.is_active', true)->firstOrFail();
        app(TenantContext::class)->set($restaurant);

        Notification::query()
            ->where(function ($query) use ($request, $restaurant) {
                $query->where('user_id', $request->user()->id)
                    ->orWhere('restaurant_id', $restaurant->id);
            })
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return response()->json(['message' => 'تم تحديد جميع الإشعارات كمقروءة.']);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'max:64'],
            'title' => ['required', 'string', 'max:200'],
            'body' => ['nullable', 'string'],
            'data' => ['nullable', 'array'],
            'channel' => ['sometimes', 'string', 'in:in_app,email,whatsapp,push'],
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'restaurant_id' => ['sometimes', 'integer', 'exists:restaurants,id'],
        ]);

        $restaurant = $request->user()->restaurants()->where('restaurants.is_active', true)->firstOrFail();
        app(TenantContext::class)->set($restaurant);

        $notification = Notification::create([
            'user_id' => $data['user_id'] ?? $request->user()->id,
            'restaurant_id' => $data['restaurant_id'] ?? $restaurant->id,
            'type' => $data['type'],
            'title' => $data['title'],
            'body' => $data['body'],
            'data' => $data['data'] ?? null,
            'channel' => $data['channel'] ?? 'in_app',
        ]);

        return response()->json($notification, 201);
    }
}
