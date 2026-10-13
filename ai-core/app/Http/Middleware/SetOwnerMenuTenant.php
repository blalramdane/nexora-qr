<?php

namespace App\Http\Middleware;

use App\Models\Restaurant;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class SetOwnerMenuTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user, 401);

        $menuId = filter_var($request->route('menu'), FILTER_VALIDATE_INT);
        abort_unless($menuId !== false && $menuId > 0, 404);

        $restaurantId = DB::table('menus')->where('id', $menuId)->value('restaurant_id');
        abort_unless($restaurantId, 404);

        $restaurant = Restaurant::query()->findOrFail($restaurantId);
        $allowed = $user->isPlatformOwner()
            || in_array($user->roleIn($restaurant), ['owner', 'manager'], true);

        abort_unless($allowed, 403);

        app(TenantContext::class)->set($restaurant);

        return $next($request);
    }
}
