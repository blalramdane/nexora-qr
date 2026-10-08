<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
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
