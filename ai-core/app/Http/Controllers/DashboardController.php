<?php

namespace App\Http\Controllers;

use App\Support\Tenancy\TenantContext;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $restaurant = app(TenantContext::class)->restaurant()->loadCount('branches');

        return Inertia::render('Dashboard', [
            'restaurant' => [
                'id' => $restaurant->id,
                'name' => $restaurant->name,
                'slug' => $restaurant->slug,
                'branches_count' => $restaurant->branches_count,
            ],
        ]);
    }
}
