<?php

namespace App\Providers;

use App\Support\Tenancy\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
    }

    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $email = Str::lower(trim((string) $request->input('email')));
            $ip = (string) $request->ip();
            $throttledResponse = static fn (Request $request, array $headers) => redirect()
                ->route('login')
                ->withErrors(['email' => 'محاولات تسجيل الدخول كثيرة. انتظر دقيقة ثم حاول مرة أخرى.'])
                ->withInput($request->only('email'));

            return [
                Limit::perMinute(5)->by('login-email|'.$ip.'|'.hash('sha256', $email))->response($throttledResponse),
                Limit::perMinute(30)->by('login-ip|'.$ip)->response($throttledResponse),
            ];
        });

        Inertia::setRootView('app');
        Inertia::share([
            'auth' => fn () => [
                'user' => auth()->user()?->only('id', 'name', 'email'),
            ],
        ]);
    }
}