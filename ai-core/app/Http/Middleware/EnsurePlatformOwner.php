<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Restricts internal cross-restaurant operations to the platform owner account. */
class EnsurePlatformOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isPlatformOwner(), 403);

        return $next($request);
    }
}
