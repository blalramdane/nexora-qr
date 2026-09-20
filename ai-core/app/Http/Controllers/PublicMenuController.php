<?php

namespace App\Http\Controllers;

use App\Models\Restaurant;
use Inertia\Inertia;
use Inertia\Response;

class PublicMenuController extends Controller
{
    public function __invoke(string $restaurantSlug): Response
    {
        $restaurant = Restaurant::query()
            ->where('slug', $restaurantSlug)
            ->where('is_active', true)
            ->with([
                'branches' => fn ($query) => $query->where('is_active', true),
            ])
            ->firstOrFail();

        $categories = $restaurant->categories()
            ->where('is_active', true)
            ->with([
                'products' => fn ($query) => $query
                    ->where('is_available', true)
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
            'categories' => $categories->map(fn ($category) => [
                'id' => $category->id,
                'name' => $category->name,
                'products' => $category->products->map(fn ($product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'price' => $product->price,
                    'image_path' => $product->image_path,
                ]),
            ]),
        ]);
    }
}