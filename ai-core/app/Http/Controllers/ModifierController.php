<?php

namespace App\Http\Controllers;

use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ModifierController extends Controller
{
    public function index(TenantContext $tenant): Response
    {
        $restaurant = $tenant->restaurant();
        return Inertia::render('Menu/Modifiers', [
            'restaurant' => $restaurant->only(['id','name']),
            'groups' => ModifierGroup::query()
                ->where('restaurant_id', $restaurant->id)
                ->with('modifiers')
                ->orderBy('sort_order')->orderBy('name')->get(),
            'products' => $restaurant->categories()->with('products:id,category_id,name')->get()->flatMap->products->values(),
        ]);
    }

    public function storeGroup(Request $request, TenantContext $tenant): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required','string','max:120'],
            'min_selections' => ['required','integer','min:0'],
            'max_selections' => ['required','integer','min:1','gte:min_selections'],
            'is_required' => ['boolean'],
        ]);

        ModifierGroup::create([
            'restaurant_id' => $tenant->id(),
            ...$data,
            'is_required' => $data['is_required'] ?? false,
        ]);

        return back();
    }

    public function storeModifier(Request $request, TenantContext $tenant): RedirectResponse
    {
        $data = $request->validate([
            'modifier_group_id' => ['required','integer'],
            'name' => ['required','string','max:120'],
            'price_delta' => ['required','numeric','min:0'],
        ]);

        $group = ModifierGroup::where('restaurant_id',$tenant->id())->findOrFail($data['modifier_group_id']);

        Modifier::create([
            'modifier_group_id' => $group->id,
            'name' => $data['name'],
            'price_delta' => $data['price_delta'],
        ]);

        return back();
    }

    public function attach(Request $request, TenantContext $tenant): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required','integer'],
            'modifier_group_id' => ['required','integer'],
        ]);

        $product = Product::where('restaurant_id',$tenant->id())->findOrFail($data['product_id']);
        $group = ModifierGroup::where('restaurant_id',$tenant->id())->findOrFail($data['modifier_group_id']);

        $product->modifierGroups()->syncWithoutDetaching([$group->id]);
        return back();
    }
}