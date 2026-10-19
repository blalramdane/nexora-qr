<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Coupon;
use App\Models\Invoice;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\Modifier;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReview;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\Template;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use LogicException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (config('app.env') === 'production') {
            throw new LogicException('DatabaseSeeder creates demonstration accounts, restaurants, menus, and orders; it is disabled in production.');
        }

        // 1. Platform templates (reusable across restaurants)
        $templates = [
            ['key' => 'fast-food', 'name' => 'Fast Food', 'description' => 'Bold, high-contrast and optimized for fast ordering.', 'category' => 'restaurant'],
            ['key' => 'cafe', 'name' => 'Café', 'description' => 'Warm, calm and compact for drinks and desserts.', 'category' => 'cafe'],
            ['key' => 'fine-dining', 'name' => 'Fine Dining', 'description' => 'Editorial, restrained and premium.', 'category' => 'restaurant', 'is_premium' => true],
        ];

        foreach ($templates as $data) {
            $template = Template::updateOrCreate(['key' => $data['key']], $data);

            $versionOne = $template->versions()->updateOrCreate(
                ['version' => 1],
                ['schema' => ['components' => ['header', 'category_nav', 'product_card', 'cart_bar'], 'variants' => ['compact', 'editorial', 'featured']], 'is_active' => $data['key'] !== 'fast-food']
            );

            if ($data['key'] === 'cafe') {
                $versionTwo = $template->versions()->updateOrCreate(
                    ['version' => 2],
                    ['schema' => [
                        'layout' => 'hero-categories-menu-detail',
                        'components' => ['sticky_header', 'hero', 'category_tiles', 'category_nav', 'product_list', 'cart_bar', 'product_sheet'],
                        'visual' => ['direction' => 'rtl', 'surface' => 'dark', 'accent' => 'coffee-gold', 'mobile_first' => true],
                    ], 'is_active' => true]
                );
                $versionTwo->activate();
            } elseif ($data['key'] === 'fast-food') {
                $versionTwo = $template->versions()->updateOrCreate(
                    ['version' => 2],
                    ['schema' => [
                        'layout' => 'hero-categories-menu-detail',
                        'components' => ['sticky_header', 'hero', 'category_tiles', 'category_nav', 'product_list', 'cart_bar', 'product_sheet'],
                        'visual' => ['direction' => 'rtl', 'surface' => 'dark', 'accent' => 'gold', 'mobile_first' => true],
                    ], 'is_active' => true]
                );
                $versionTwo->activate();
            } elseif ($data['key'] === 'fine-dining') {
                $versionTwo = $template->versions()->updateOrCreate(
                    ['version' => 2],
                    ['schema' => [
                        'layout' => 'editorial-hero-curated-menu-detail',
                        'components' => ['minimal_header', 'hero', 'story_strip', 'category_nav', 'editorial_product_list', 'cart_bar', 'product_sheet'],
                        'visual' => ['direction' => 'rtl', 'surface' => 'dark', 'accent' => 'champagne-gold', 'mobile_first' => true, 'typography' => 'serif'],
                    ], 'is_active' => true]
                );
                $versionTwo->activate();
            } else {
                $versionOne->activate();
            }
        }

        // 2. Demo restaurant + owner user
        $owner = User::create([
            'name' => 'أحمد محمد',
            'email' => 'owner@nexora.test',
            'password' => Hash::make('password'),
            'is_platform_owner' => false,
        ]);

        $restaurant = Restaurant::create([
            'name' => 'مطعم نكسورة للتوصيل',
            'slug' => 'nexora-delivery',
            'phone' => '01012345678',
            'whatsapp_phone' => '01012345678',
            'delivery_fee' => 25.00,
            'delivery_areas' => ['المعادي', 'المقطم', 'المدينة المنورة', 'المعادى'],
            'delivery_enabled' => true,
            'pickup_enabled' => true,
            'logo_path' => null,
            'timezone' => 'Africa/Cairo',
            'is_active' => true,
        ]);

        $restaurant->users()->attach($owner->id, ['role' => 'owner']);

        $branch = Branch::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'الفرع الرئيسي',
            'slug' => 'main',
            'supported_order_types' => ['dine_in', 'pickup', 'delivery'],
        ]);

        // Table for dine-in QR
        RestaurantTable::create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'name' => 'ترابيزة 1',
            'slug' => 'table-1',
            'qr_token_hash' => hash('sha256', 'table-1-token'),
            'is_active' => true,
            'capacity' => 4,
        ]);

        // 3. Menu with Fast Food template
        $template = Template::where('key', 'fast-food')->first();
        $activeVersion = $template->activeVersion()->first();

        $menu = Menu::create([
            'restaurant_id' => $restaurant->id,
            'template_id' => $template->id,
            'template_version_id' => $activeVersion?->id,
            'name' => 'قائمة الوجبات السريعة',
            'slug' => 'fast-food-menu',
            'theme' => [
                'primary' => '#c9953c',
                'accent' => '#e7b55d',
                'background' => '#090807',
                'foreground' => '#f8efe0',
                'radius' => 'xl',
            ],
            'is_published' => true,
            'published_at' => now(),
        ]);

        // 4. Categories and Products
        $burgersCategory = MenuCategory::create([
            'menu_id' => $menu->id,
            'restaurant_id' => $restaurant->id,
            'name' => 'برجر',
            'slug' => 'burgers',
            'description' => 'برجر طازج ومحمص',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $drinksCategory = MenuCategory::create([
            'menu_id' => $menu->id,
            'restaurant_id' => $restaurant->id,
            'name' => 'مشروبات',
            'slug' => 'drinks',
            'description' => 'مشروبات باردة وساخنة',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $dessertsCategory = MenuCategory::create([
            'menu_id' => $menu->id,
            'restaurant_id' => $restaurant->id,
            'name' => 'حلويات',
            'slug' => 'desserts',
            'description' => 'أصناف حلويات متنوعة',
            'is_active' => true,
            'sort_order' => 3,
        ]);

        // Burgers
        $classicBurger = Product::create([
            'menu_category_id' => $burgersCategory->id,
            'restaurant_id' => $restaurant->id,
            'name' => 'برجر كلاسيك',
            'slug' => 'classic-burger',
            'description' => 'برجر لحم بقري مع خس، طماطم، وخضروات طازجة',
            'price' => 85.00,
            'image_path' => null,
            'is_available' => true,
            'is_featured' => true,
            'sort_order' => 1,
        ]);

        ProductVariant::create([
            'product_id' => $classicBurger->id,
            'restaurant_id' => $restaurant->id,
            'name' => 'حجم صغير',
            'price' => 85.00,
            'price_delta' => 0,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        ProductVariant::create([
            'product_id' => $classicBurger->id,
            'restaurant_id' => $restaurant->id,
            'name' => 'حجم كبير',
            'price' => 110.00,
            'price_delta' => 25.00,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        Modifier::create([
            'product_id' => $classicBurger->id,
            'restaurant_id' => $restaurant->id,
            'name' => 'جبنة إضافية',
            'price_delta' => 15.00,
            'is_active' => true,
            'is_required' => false,
            'group_name' => 'إضافات',
            'group_min_choices' => 0,
            'group_max_choices' => 2,
            'sort_order' => 1,
        ]);

        Modifier::create([
            'product_id' => $classicBurger->id,
            'restaurant_id' => $restaurant->id,
            'name' => 'لحم مضاعف',
            'price_delta' => 30.00,
            'is_active' => true,
            'is_required' => false,
            'group_name' => 'إضافات',
            'group_min_choices' => 0,
            'group_max_choices' => 2,
            'sort_order' => 2,
        ]);

        $chickenBurger = Product::create([
            'menu_category_id' => $burgersCategory->id,
            'restaurant_id' => $restaurant->id,
            'name' => 'برجر دجاج',
            'slug' => 'chicken-burger',
            'description' => 'برجر دجاج مشوي مع مايونيز وثوم',
            'price' => 75.00,
            'image_path' => null,
            'is_available' => true,
            'is_featured' => false,
            'sort_order' => 2,
        ]);

        // Drinks
        $cola = Product::create([
            'menu_category_id' => $drinksCategory->id,
            'restaurant_id' => $restaurant->id,
            'name' => 'كوكا كولا',
            'slug' => 'cola',
            'description' => 'مشروب غازي بارد',
            'price' => 15.00,
            'image_path' => null,
            'is_available' => true,
            'is_featured' => true,
            'sort_order' => 1,
        ]);

        $coffee = Product::create([
            'menu_category_id' => $drinksCategory->id,
            'restaurant_id' => $restaurant->id,
            'name' => 'إسبريسو',
            'slug' => 'espresso',
            'description' => 'قهوة إسبريسو مركزة',
            'price' => 25.00,
            'image_path' => null,
            'is_available' => true,
            'is_featured' => false,
            'sort_order' => 2,
        ]);

        // Desserts
        $cake = Product::create([
            'menu_category_id' => $dessertsCategory->id,
            'restaurant_id' => $restaurant->id,
            'name' => 'كعكة شوكولاتة',
            'slug' => 'chocolate-cake',
            'description' => 'كعكة شوكولاتة غنية',
            'price' => 45.00,
            'image_path' => null,
            'is_available' => true,
            'is_featured' => true,
            'sort_order' => 1,
        ]);

        // 5. Coupons
        Coupon::create([
            'restaurant_id' => $restaurant->id,
            'code' => 'WELCOME10',
            'type' => 'percentage',
            'value' => 10.00,
            'minimum_subtotal' => 100.00,
            'maximum_discount' => 50.00,
            'usage_limit' => 100,
            'usage_count' => 0,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'is_active' => true,
        ]);

        Coupon::create([
            'restaurant_id' => $restaurant->id,
            'code' => 'SAVE20',
            'type' => 'fixed',
            'value' => 20.00,
            'minimum_subtotal' => 150.00,
            'maximum_discount' => null,
            'usage_limit' => 50,
            'usage_count' => 0,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'is_active' => true,
        ]);

        // 6. Demo orders
        $order1 = Order::create([
            'restaurant_id' => $restaurant->id,
            'menu_id' => $menu->id,
            'source' => 'qr_menu',
            'payment_method' => 'cash_on_delivery',
            'payment_status' => 'paid',
            'order_number' => 'NX-'.now()->subDays(2)->format('ymd').'-ABC123',
            'customer_name' => 'محمد علي',
            'customer_phone' => '01098765432',
            'fulfillment_type' => 'delivery',
            'delivery_address' => 'القاهرة، المعادي، شارع 9',
            'delivery_area' => 'المعادي',
            'status' => 'delivered',
            'items_count' => 2,
            'subtotal' => 100.00,
            'delivery_fee' => 25.00,
            'discount_amount' => 0,
            'total' => 125.00,
            'notes' => 'من فضلك دون بصل',
        ]);

        OrderItem::create([
            'order_id' => $order1->id,
            'product_id' => $classicBurger->id,
            'product_name' => 'برجر كلاسيك',
            'variant_name' => 'حجم كبير',
            'modifiers' => json_encode([['id' => 1, 'name' => 'جبنة إضافية', 'price' => 15.00]]),
            'quantity' => 1,
            'unit_price' => 110.00,
            'line_total' => 125.00,
        ]);

        $order2 = Order::create([
            'restaurant_id' => $restaurant->id,
            'menu_id' => $menu->id,
            'source' => 'qr_menu',
            'payment_method' => 'cash_on_delivery',
            'payment_status' => 'unpaid',
            'order_number' => 'NX-'.now()->subDays(1)->format('ymd').'-DEF456',
            'customer_name' => 'سارة أحمد',
            'customer_phone' => '01122334455',
            'fulfillment_type' => 'pickup',
            'status' => 'new',
            'items_count' => 1,
            'subtotal' => 75.00,
            'delivery_fee' => 0,
            'discount_amount' => 0,
            'total' => 75.00,
            'notes' => null,
        ]);

        OrderItem::create([
            'order_id' => $order2->id,
            'product_id' => $chickenBurger->id,
            'product_name' => 'برجر دجاج',
            'variant_name' => null,
            'modifiers' => json_encode([]),
            'quantity' => 1,
            'unit_price' => 75.00,
            'line_total' => 75.00,
        ]);

        // 7. Invoice for order1
        $invoice = Invoice::create([
            'restaurant_id' => $restaurant->id,
            'order_id' => $order1->id,
            'invoice_number' => (new Invoice)->generateInvoiceNumber(),
            'customer_name' => 'محمد علي',
            'customer_phone' => '01098765432',
            'customer_email' => null,
            'fulfillment_type' => 'delivery',
            'payment_method' => 'cash_on_delivery',
            'payment_status' => 'paid',
            'subtotal' => 100.00,
            'delivery_fee' => 25.00,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total' => 125.00,
            'notes' => null,
            'status' => 'paid',
            'issued_at' => now(),
            'paid_at' => now(),
        ]);

        $order1->update(['invoice_id' => $invoice->id]);

        // 8. Reviews
        OrderReview::create([
            'restaurant_id' => $restaurant->id,
            'order_id' => $order1->id,
            'customer_id' => $owner->id,
            'menu_id' => $menu->id,
            'rating' => 5,
            'comment' => 'البرجر لذيذ جداً والتوصيل كان سريع. أنصح بالتجربة.',
            'rating_breakdown' => json_encode(['food' => 5, 'delivery' => 5, 'service' => 4, 'packaging' => 5]),
            'is_approved' => true,
            'is_published' => true,
            'published_at' => now(),
        ]);

        OrderReview::create([
            'restaurant_id' => $restaurant->id,
            'order_id' => $order2->id,
            'customer_id' => $owner->id,
            'menu_id' => $menu->id,
            'rating' => 4,
            'comment' => 'طلب ممتاز، لكن التأخير كان بسيط.',
            'rating_breakdown' => json_encode(['food' => 4, 'delivery' => 3, 'service' => 4, 'packaging' => 4]),
            'is_approved' => true,
            'is_published' => true,
            'published_at' => now(),
        ]);
    }
}
