<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class OwnerAccountController extends Controller
{
    public function show(Request $request): Response
    {
        abort_unless($request->user()->isPlatformOwner(), 403);

        return Inertia::render('Owner/Account', [
            'user' => [
                'name' => $request->user()->name,
                'email' => $request->user()->email,
            ],
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isPlatformOwner(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$request->user()->id],
            'current_password' => ['required', 'current_password'],
        ]);

        $request->user()->forceFill([
            'name' => trim($data['name']),
            'email' => strtolower(trim($data['email'])),
        ])->save();

        return back()->with('success', 'تم تحديث اسم الحساب والإيميل.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isPlatformOwner(), 403);

        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
        ]);

        $request->user()->forceFill(['password' => $data['password']])->save();

        return back()->with('success', 'تم تغيير كلمة السر. استخدم الجديدة في تسجيل الدخول القادم.');
    }
}
