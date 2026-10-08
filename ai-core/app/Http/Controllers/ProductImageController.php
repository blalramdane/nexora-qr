<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductImageController extends Controller
{
    public function store(Request $request, int $menu, int $category, int $product): JsonResponse
    {
        $this->authorizeMenuManagement();

        $menuModel = Menu::query()->findOrFail($menu);
        $categoryModel = $menuModel->categories()->whereKey($category)->firstOrFail();
        $productModel = $categoryModel->products()->whereKey($product)->firstOrFail();

        $data = $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ]);

        $disk = Storage::disk('public');
        $tenantId = app(TenantContext::class)->id();
        $oldPath = $productModel->image_path;

        $path = $data['image']->storeAs(
            'restaurants/'.$tenantId.'/products',
            Str::uuid()->toString().'.'.$data['image']->extension(),
            'public'
        );

        $productModel->update(['image_path' => $path]);

        if ($oldPath && $oldPath !== $path && $disk->exists($oldPath)) {
            $disk->delete($oldPath);
        }

        return response()->json([
            'path' => $path,
            'url' => $disk->url($path),
        ]);
    }

    public function destroy(int $menu, int $category, int $product): JsonResponse
    {
        $this->authorizeMenuManagement();

        $menuModel = Menu::query()->findOrFail($menu);
        $categoryModel = $menuModel->categories()->whereKey($category)->firstOrFail();
        $productModel = $categoryModel->products()->whereKey($product)->firstOrFail();

        $path = $productModel->image_path;

        if ($path) {
            $disk = Storage::disk('public');
            if ($disk->exists($path)) {
                $disk->delete($path);
            }

            $productModel->update(['image_path' => null]);
        }

        return response()->json(['path' => null]);
    }

    private function authorizeMenuManagement(): void
    {
        $user = request()->user();
        $restaurant = app(TenantContext::class)->restaurant();

        abort_unless(
            $user?->isOwnerOf($restaurant) || $user?->roleIn($restaurant) === 'manager',
            403
        );
    }
}
