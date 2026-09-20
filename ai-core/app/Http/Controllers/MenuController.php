<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MenuController extends Controller
{
    public function index(TenantContext $tenant): Response
    {
        $restaurant = $tenant->restaurant();

        return Inertia::render('Menu/Index', [
            'restaurant' => [
                'id' => $restaurant->id,
                'name' => $restaurant->name,
            ],
            'categories' => Category::query()
                ->where('restaurant_id', $restaurant->id)
                ->withCount('products')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (Category $category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'is_active' => $category->is_active,
                    'products_count' => $category->products_count,
                ]),
            'products' => Product::query()
                ->where('restaurant_id', $restaurant->id)
                ->with('category:id,name')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (Product $product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'category' => $product->category?->name,
                    'price' => $product->price,
                    'is_available' => $product->is_available,
                    'is_featured' => $product->is_featured,
                ]),
        ]);
    }

    public function storeCategory(Request $request, TenantContext $tenant)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $restaurant = $tenant->restaurant();

        $slug = str($data['name'])->slug()->value();
        $baseSlug = $slug ?: 'category';
        $slug = $baseSlug;
        $suffix = 2;

        while (Category::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('slug', $slug)
            ->exists()) {
            $slug = $baseSlug.'-'.$suffix++;
        }

        Category::create([
            'restaurant_id' => $restaurant->id,
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
        ]);

        return back();
    }

    public function storeProduct(Request $request, TenantContext $tenant)
    {
        $data = $request->validate([
            'category_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0'],
        ]);

        $restaurant = $tenant->restaurant();

        $category = Category::query()
            ->where('restaurant_id', $restaurant->id)
            ->findOrFail($data['category_id']);

        $slug = str($data['name'])->slug()->value();
        $baseSlug = $slug ?: 'product';
        $slug = $baseSlug;
        $suffix = 2;

        while (Product::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('slug', $slug)
            ->exists()) {
            $slug = $baseSlug.'-'.$suffix++;
        }

        Product::create([
            'restaurant_id' => $restaurant->id,
            'category_id' => $category->id,
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'price' => $data['price'],
        ]);

        return back();
    }
}