<?php

namespace App\Providers;

use App\Support\Tenancy\TenantContext;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
    }

    public function boot(): void
    {
        Inertia::setRootView('app');
        Inertia::share([
            'auth' => fn () => [
                'user' => auth()->user()?->only('id', 'name', 'email'),
            ],
        ]);
    }
}
