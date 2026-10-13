<?php

namespace App\Http\Middleware;

use App\Models\Restaurant;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetOwnerRestaurantTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user, 401);

        $restaurantId = filter_var($request->route('restaurant'), FILTER_VALIDATE_INT);
        abort_unless($restaurantId !== false && $restaurantId > 0, 404);

        $restaurant = Restaurant::query()->findOrFail($restaurantId);
        $allowed = $user->isPlatformOwner()
            || in_array($user->roleIn($restaurant), ['owner', 'manager'], true);

        abort_unless($allowed, 403);

        app(TenantContext::class)->set($restaurant);

        return $next($request);
    }
}
