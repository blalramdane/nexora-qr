<?php

namespace App\Http\Controllers;

use App\Models\RestaurantTable;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TableController extends Controller
{
    public function index(TenantContext $tenant): Response
    {
        $tables = RestaurantTable::query()
            ->where('restaurant_id', $tenant->id())
            ->with('branch:id,name')
            ->withCount(['orders as active_orders_count' => fn ($query) => $query->whereIn('status', ['pending','confirmed','preparing','ready'])])
            ->orderBy('name')
            ->get();

        return Inertia::render('Tables/Index', [
            'restaurant' => $tenant->restaurant()->only(['id', 'name']),
            'tables' => $tables->map(fn ($table) => [
                'id' => $table->id,
                'name' => $table->name,
                'token' => $table->token,
                'capacity' => $table->capacity,
                'branch' => $table->branch?->name,
                'is_active' => $table->is_active,
                'active_orders_count' => $table->active_orders_count,
                'menu_url' => url('/m/'.$tenant->restaurant()->slug.'?table='.$table->token),
            ]),
        ]);
    }

    public function store(Request $request, TenantContext $tenant): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $branch = $tenant->restaurant()->branches()->where('is_active', true)->first();

        RestaurantTable::create([
            'restaurant_id' => $tenant->id(),
            'branch_id' => $branch?->id,
            'name' => $data['name'],
            'token' => Str::random(32),
            'capacity' => $data['capacity'] ?? null,
            'is_active' => true,
        ]);

        return back();
    }

    public function toggle(RestaurantTable $table, TenantContext $tenant): RedirectResponse
    {
        abort_unless($table->restaurant_id === $tenant->id(), 404);
        $table->update(['is_active' => !$table->is_active]);
        return back();
    }
}