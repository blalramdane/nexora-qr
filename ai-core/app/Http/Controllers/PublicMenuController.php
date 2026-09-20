<?php

namespace App\Http\Controllers;

use App\Models\Restaurant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicMenuController extends Controller
{
    public function __invoke(Request $request, string $restaurantSlug): Response
    {
        $restaurant = Restaurant::query()
            ->where('slug', $restaurantSlug)
            ->where('is_active', true)
            ->firstOrFail();

        $table = null;
        if ($request->filled('table')) {
            $table = $restaurant->tables()
                ->where('token', $request->string('table')->toString())
                ->where('is_active', true)
                ->first();
        }

        $categories = $restaurant->categories()
            ->where('is_active', true)
            ->with([
                'products' => fn ($query) => $query
                    ->where('is_available', true)
                    ->with(['modifierGroups.modifiers' => fn ($q) => $q->where('is_available', true)->orderBy('sort_order')])
                    ->orderBy('sort_order')
                    ->orderBy('name'),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('PublicMenu/Show', [
            'restaurant' => [
                'name' => $restaurant->name,
                'slug' => $restaurant->slug,
                'phone' => $restaurant->phone,
            ],
            'table' => $table ? ['name' => $table->name, 'token' => $table->token] : null,
            'categories' => $categories->map(fn ($category) => [
                'id' => $category->id,
                'name' => $category->name,
                'products' => $category->products->map(fn ($product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'price' => $product->price,
                    'image_path' => $product->image_path,
                    'modifier_groups' => $product->modifierGroups->map(fn ($group) => [
                        'id' => $group->id,
                        'name' => $group->name,
                        'min_selections' => $group->min_selections,
                        'max_selections' => $group->max_selections,
                        'is_required' => $group->is_required,
                        'modifiers' => $group->modifiers->map(fn ($modifier) => [
                            'id' => $modifier->id,
                            'name' => $modifier->name,
                            'price_delta' => $modifier->price_delta,
                        ]),
                    ]),
                ]),
            ]),
        ]);
    }
}