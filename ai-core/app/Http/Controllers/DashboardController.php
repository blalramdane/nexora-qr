<?php

namespace App\Http\Controllers;

use App\Support\Tenancy\TenantContext;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(TenantContext $tenant): Response
    {
        abort_unless($tenant->check(), 403);

        return Inertia::render('Dashboard', [
            'restaurant' => $tenant->restaurant()->only(['id', 'name', 'slug']),
            'branchCount' => $tenant->restaurant()->branches()->count(),
        ]);
    }
}
