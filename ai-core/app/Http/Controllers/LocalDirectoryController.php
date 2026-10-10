<?php

namespace App\Http\Controllers;

use App\Models\LocalBusiness;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LocalDirectoryController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'area' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
        ]);

        $businesses = LocalBusiness::query()->publiclyListed()
            ->when($filters['q'] ?? null, fn ($query, $term) => $query->where(function ($q) use ($term) {
                $q->where('name', 'like', '%'.$term.'%')->orWhere('category', 'like', '%'.$term.'%');
            }))
            ->when($filters['city'] ?? null, fn ($query, $city) => $query->where('city', $city))
            ->when($filters['area'] ?? null, fn ($query, $area) => $query->where('area', $area))
            ->when($filters['category'] ?? null, fn ($query, $category) => $query->where('category', $category))
            ->orderBy('city')->orderBy('area')->orderBy('name')
            ->paginate(12)->withQueryString();

        return Inertia::render('Local/Directory', [
            'businesses' => $businesses,
            'filters' => $filters,
            'cities' => ['دمياط', 'الزرقا'],
            'areas' => LocalBusiness::query()->publiclyListed()->whereNotNull('area')->distinct()->orderBy('area')->pluck('area')->values(),
            'categories' => LocalBusiness::query()->publiclyListed()->distinct()->orderBy('category')->pluck('category')->values(),
        ]);
    }

    public function show(string $slug): Response
    {
        $record = LocalBusiness::query()->publiclyListed()->where('slug', $slug)->firstOrFail();
        $business = $record->only([
            'name', 'slug', 'city', 'area', 'address', 'category', 'phone',
            'website_url', 'maps_url', 'menu_url', 'source_url',
        ]);

        return Inertia::render('Local/Business', ['business' => $business]);
    }
}
