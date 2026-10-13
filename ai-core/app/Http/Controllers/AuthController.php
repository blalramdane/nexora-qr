<?php

namespace App\Http\Controllers;

use App\Models\Restaurant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function showLogin(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function showForgotPassword(): Response
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function sendPasswordResetLink(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);

        if (in_array(config('mail.default'), ['log', 'array'], true)) {
            return back()->withErrors(['email' => 'إعداد البريد في النظام لا يرسل رسائل فعلية حاليًا. تواصل مع مسؤول النظام لضبط البريد ثم أعد المحاولة.']);
        }

        try {
            PasswordBroker::sendResetLink(['email' => mb_strtolower(trim($data['email']))]);
        } catch (\Throwable $exception) {
            Log::warning('NEXORA password recovery email delivery failed.', ['exception' => $exception::class]);

            return back()->withErrors(['email' => 'تعذر إرسال رسالة الاستعادة حاليًا. حاول لاحقًا أو تواصل مع مسؤول النظام.']);
        }

        return back()->with('status', 'لو البريد مسجل في النظام، هتوصلك رسالة فيها رابط آمن لتعيين كلمة السر.');
    }

    public function showResetPassword(Request $request, string $token): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
        ]);

        $status = PasswordBroker::reset($data, function ($user, string $password): void {
            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();

            event(new PasswordReset($user));
        });

        if ($status === PasswordBroker::PASSWORD_RESET) {
            return to_route('login')->with('success', 'تم تعيين كلمة السر بنجاح. سجّل الدخول بكلمة السر الجديدة.');
        }

        return back()->withErrors(['email' => __($status)]);
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ]);

        if (!Auth::attempt(
            ['email' => $credentials['email'], 'password' => $credentials['password']],
            (bool) ($credentials['remember'] ?? false)
        )) {
            return back()->withErrors(['email' => 'بيانات الدخول غير صحيحة.']);
        }

        $request->session()->regenerate();

        if ($request->user()->deliveryRider()->exists() && !$request->user()->restaurants()->exists()) {
            // Ignore a stale intended URL from a previous account/session.
            // Otherwise a restaurant owner can be sent back to the rider hub.
            return redirect()->route('delivery.dashboard');
        }

        if ($request->user()->isPlatformOwner() && $request->user()->restaurants()->where('restaurants.is_active', true)->doesntExist()) {
            return redirect()->route('owner.menus.index');
        }

        // Always send restaurant accounts to their own dashboard after login.
        return redirect()->route('dashboard');
    }

    public function showRegister(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'restaurant_name' => ['required', 'string', 'max:160'],
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = \App\Models\User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            $restaurant = Restaurant::create([
                'name' => $data['restaurant_name'],
                'slug' => Str::slug($data['restaurant_name']).'-'.Str::lower(Str::random(6)),
            ]);

            $restaurant->users()->attach($user->id, ['role' => 'owner']);
            app(TenantContext::class)->set($restaurant);

            $restaurant->branches()->create([
                'name' => 'الفرع الرئيسي',
                'slug' => 'main',
                'supported_order_types' => ['dine_in', 'pickup'],
            ]);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
