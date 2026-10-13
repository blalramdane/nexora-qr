<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\EnsurePlatformOwner;
use App\Http\Middleware\SetOwnerMenuTenant;
use App\Http\Middleware\SetOwnerRestaurantTenant;
use App\Http\Middleware\SetTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The app container is reachable only through the private Compose network.
        // Trust the forwarded HTTPS/host headers from the Caddy reverse proxy.
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );

        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'tenant' => SetTenant::class,
            'owner-menu-tenant' => SetOwnerMenuTenant::class,
            'owner-restaurant-tenant' => SetOwnerRestaurantTenant::class,
            'platform-owner' => EnsurePlatformOwner::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
