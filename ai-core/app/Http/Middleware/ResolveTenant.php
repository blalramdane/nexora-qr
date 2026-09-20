<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) return $next($request);

        $restaurantId = $request->session()->get('active_restaurant_id');
        $restaurant = $user->restaurants()
            ->wherePivot('is_active', true)
            ->when($restaurantId, fn ($q) => $q->whereKey($restaurantId))
            ->where('restaurants.is_active', true)
            ->first();

        if (!$restaurant) {
            $restaurant = $user->restaurants()
                ->wherePivot('is_active', true)
                ->where('restaurants.is_active', true)
                ->first();
            if ($restaurant) $request->session()->put('active_restaurant_id', $restaurant->id);
        }

        app(TenantContext::class)->set($restaurant);
        return $next($request);
    }
}