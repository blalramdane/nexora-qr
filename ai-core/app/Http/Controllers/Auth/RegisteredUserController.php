<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'restaurant_name' => ['required', 'string', 'max:160'],
            'restaurant_phone' => ['nullable', 'string', 'max:40'],
        ]);

        [$user, $restaurant] = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            $restaurant = Restaurant::create([
                'name' => $data['restaurant_name'],
                'phone' => $data['restaurant_phone'] ?? null,
                'slug' => Str::slug($data['restaurant_name']).'-'.Str::lower(Str::random(6)),
            ]);

            $restaurant->users()->attach($user->id, ['role' => 'owner', 'is_active' => true]);
            $restaurant->branches()->create([
                'name' => 'Main Branch',
                'slug' => 'main',
                'is_active' => true,
            ]);

            return [$user, $restaurant];
        });

        auth()->login($user);
        $request->session()->regenerate();
        $request->session()->put('active_restaurant_id', $restaurant->id);

        return redirect()->route('dashboard');
    }
}
