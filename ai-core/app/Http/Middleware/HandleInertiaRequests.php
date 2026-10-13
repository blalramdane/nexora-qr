<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function handle(Request $request, Closure $next)
    {
        $authenticated = $request->user() !== null;
        $response = parent::handle($request, $next);

        if ($authenticated) {
            // Avoid restoring protected account pages from the browser cache after logout.
            $response->headers->set('Cache-Control', 'private, no-store, no-cache, must-revalidate');
            $response->headers->set('Pragma', 'no-cache');
        }

        return $response;
    }
    public function version(Request $request): ?string
    {
        if (app()->environment('testing')) {
            return null;
        }

        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $request->user()?->only('id', 'name', 'email'),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'status' => fn () => $request->session()->get('status'),
            ],
        ]);
    }
}
