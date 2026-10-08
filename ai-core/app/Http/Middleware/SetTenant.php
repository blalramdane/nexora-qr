<?php

namespace App\Http\Middleware;

use App\Models\Restaurant;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user, 401);

        $restaurant = $user->restaurants()
            ->where('restaurants.is_active', true)
            ->first();

        abort_unless($restaurant instanceof Restaurant, 403, 'No active restaurant membership.');

        app(TenantContext::class)->set($restaurant);

        return $next($request);
    }
}
