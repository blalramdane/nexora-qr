<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use App\Models\Restaurant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Menu-grounded first version of the NEXORA AI Waiter.
 * It never invents products or prices and never creates an order itself.
 */
class AiWaiterController extends Controller
{
    public function chat(Request $request, string $restaurantSlug, string $menuSlug): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'min:1', 'max:1000'],
        ]);

        $restaurant = Restaurant::query()
            ->where('slug', $restaurantSlug)
            ->where('is_active', true)
            ->firstOrFail();

        app(TenantContext::class)->set($restaurant);

        $menu = Menu::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('slug', $menuSlug)
            ->where('is_published', true)
            ->with(['categories' => fn ($query) => $query->where('is_active', true),
                'categories.products' => fn ($query) => $query->where('is_available', true),
                'categories.products.modifiers' => fn ($query) => $query->where('is_active', true)])
            ->firstOrFail();

        $message = Str::lower(trim($data['message']));
        $products = $menu->categories->flatMap(fn ($category) => $category->products->map(fn ($product) => [
            'id' => $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'price' => (float) $product->price,
            'category' => $category->name,
            'requires_options' => $product->modifiers->contains(fn ($modifier) => $modifier->is_required || ($modifier->group_name && (int) $modifier->group_min_choices > 0)),
        ]))->values();

        if ($products->isEmpty()) {
            return response()->json([
                'reply' => 'المنيو مفيهوش أصناف متاحة حاليًا. تقدر تتواصل مع المطعم للتأكد من التوافر.',
                'products' => [],
                'can_order' => false,
            ]);
        }

        $tokens = collect(preg_split('/[^\p{L}\p{N}]+/u', $message, -1, PREG_SPLIT_NO_EMPTY))
            ->filter(fn ($token) => mb_strlen($token) >= 3)
            ->values();

        $ranked = $products->map(function (array $product) use ($tokens, $message) {
            $haystack = Str::lower($product['name'].' '.$product['description'].' '.$product['category']);
            $score = $tokens->sum(fn ($token) => str_contains($haystack, $token) ? 1 : 0);
            if (preg_match('/(رخيص|اقتصادي|ميزاني|cheap|budget)/u', $message) && $product['price'] <= 100) {
                $score += 2;
            }
            if (preg_match('/(غالي|فاخر|premium)/u', $message) && $product['price'] >= 150) {
                $score += 2;
            }
            return ['product' => $product, 'score' => $score];
        })->sortByDesc('score')->values();

        $matched = $ranked->filter(fn ($item) => $item['score'] > 0)->take(3)->pluck('product')->values();
        $suggestions = ($matched->isNotEmpty() ? $matched : $products->take(3))->values();

        $intro = $matched->isNotEmpty()
            ? 'لقيت لك اختيارات مناسبة من منيو '. $restaurant->name .':'
            : 'أقدر أساعدك تختار من الأصناف المتاحة دي. قولّي بتحب إيه أو ميزانيتك كام:';

        return response()->json([
            'reply' => $intro,
            'products' => $suggestions,
            'can_order' => true,
            'disclaimer' => 'الأسعار والتوافر حسب المنيو الحالي، وتأكيد الطلب بيتم في سلة الطلبات.',
        ]);
    }
}
