<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Restaurant;
use App\Models\Template;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Idempotently imports the menu shown in the owner-provided Al Saqa flyers.
 * Run with: php artisan db:seed --class=Database\\Seeders\\AlSaqaMenuSeeder
 *
 * Product photos are illustrative real-food photographs, not claimed to be
 * photographs of Al Saqa's own plated products. Prices come from the supplied
 * menu artwork; uncertain wording is kept conservative in descriptions.
 */
class AlSaqaMenuSeeder extends Seeder
{
    public function run(): void
    {
        if (config('app.env') === 'production' && ! app()->runningInConsole()) {
            throw new \LogicException('Al Saqa menu import must run from an authorized CLI session.');
        }

        DB::transaction(function (): void {
            // Reuse the known existing live tenant first. Creating `al-saqa`
            // unconditionally would duplicate the `al-saqa-al-zarqa` tenant
            // that the production menu-recovery migration already targets.
            $restaurant = Restaurant::query()->where('slug', 'al-saqa-al-zarqa')->first()
                ?? Restaurant::query()->where('slug', 'al-saqa')->first();

            if (! $restaurant) {
                $restaurant = Restaurant::query()->create([
                    'name' => 'مطعم السقا',
                    'slug' => 'al-saqa-al-zarqa',
                    'is_active' => true,
                    'timezone' => 'Africa/Cairo',
                ]);
            }

            $restaurant->forceFill([
                'name' => 'مطعم السقا',
                'phone' => $restaurant->phone ?: '01028287276',
                'whatsapp_phone' => $restaurant->whatsapp_phone ?: '01028287276',
                'delivery_enabled' => true,
                'pickup_enabled' => true,
                'timezone' => $restaurant->timezone ?: 'Africa/Cairo',
                'is_active' => true,
            ])->save();

            // Establish tenant context before querying the branch relationship;
            // otherwise a tenant global scope can hide its existing main branch.
            app(TenantContext::class)->set($restaurant);

            $restaurant->branches()->firstOrCreate(
                ['slug' => 'main'],
                [
                    'name' => 'الفرع الرئيسي',
                    'phone' => '01028287276',
                    'address' => 'مدينة الزرقا، أمام مجلس المدينة',
                    'supported_order_types' => ['dine_in', 'pickup', 'delivery'],
                    'is_active' => true,
                ]
            );

            $template = Template::query()->where('key', 'fast-food')->where('is_active', true)->firstOrFail();
            $version = $template->activeVersion()->firstOrFail();

            $menu = Menu::query()->withoutGlobalScopes()->firstOrCreate(
                ['restaurant_id' => $restaurant->id, 'slug' => 'al-saqa-menu'],
                ['name' => 'قائمة السقا', 'template_id' => $template->id, 'template_version_id' => $version->id]
            );

            $menu->forceFill([
                'name' => 'منيو مطعم السقا',
                'template_id' => $template->id,
                'template_version_id' => $version->id,
                'theme' => [
                    'primary' => '#A90F20',
                    'accent' => '#F4C542',
                    'background' => '#100D10',
                    'foreground' => '#FFF8E9',
                    'radius' => 'xl',
                ],
                'is_published' => true,
                'published_at' => $menu->published_at ?: now(),
            ])->save();

            app(TenantContext::class)->set($restaurant);

            $photos = [
                // Publicly available food photographs; see the source URLs in the values.
                'hawawshi' => 'https://scenenow.com/Content/editor_api/images/Hawawshi-Egyptian-Meat-Stuffed-Bread-3-of-3-90e52b81-5bf2-4c4f-a3d2-3600341e7a06.jpeg',
                'liver' => 'https://s3-eu-west-1.amazonaws.com/elmenusv5-stg/Normal/47b66e70-5b97-49d7-9a83-03ba4e78f936.jpg',
                'kaware3' => 'https://www.vetogate.com/Upload/libfiles/550/9/428.jpg',
                'macaroni' => 'https://s3-eu-west-1.amazonaws.com/elmenusv5-stg/Normal/924cf90a-4c5a-40f4-ad31-b0090b409f2e.jpg',
                'grill' => 'https://commons.wikimedia.org/wiki/Special:FilePath/Mixed_grill_-_Khan_el-Khalili_-_Cairo%2C_Egypt.jpg?width=1000',
                'mahshi' => 'https://cdn.thefork.com/tf-lab/image/upload/w_500%2Ch_500%2Cc_fill%2Cq_auto%2Cf_jpg/restaurant/36b848ab-3866-4182-b6ef-347b9cf41ec7/e1b4fa54-4083-481f-9291-9a83d052c146.jpg',
                'salad' => 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?auto=format&fit=crop&w=1000&q=85',
            ];

            $sections = [
                [
                    'name' => 'صواني السقا', 'slug' => 'saqa-trays', 'description' => 'صواني مشاركة ومشاوي منيو السقا.', 'photo' => 'grill',
                    'items' => [
                        ['صينية ٣ أفراد', 400, 'نصف مشكل + نصف مميز + ٢ حواوشي + ٢ شوربة كوارع'],
                        ['صينية ٤ أفراد', 600, 'مشكل + نصف مميز + ٢ حواوشي + ٣ شوربة كوارع'],
                        ['صينية الأبطال', 800, 'كيلو مشكل + نصف مميز + ٣ حواوشي + ٤ شوربة كوارع سادة + ٣ بيبسي + مياه كبيرة'],
                    ],
                ],
                [
                    'name' => 'مطبخ السقا', 'slug' => 'saqa-kitchen', 'description' => 'طواجن وأطباق كوارع وأرز.', 'photo' => 'kaware3',
                    'items' => [
                        ['طاجن كوارع بورق العنب', 200, null],
                        ['طاجن كوارع بالبصل', 200, null],
                        ['طاجن كوارع فرن', 200, null],
                        ['طاجن فتة بالكوارع', 200, null],
                        ['طبق ورق عنب', 50, null],
                        ['مميز محمر', 100, 'السعر حسب الحجم: من ١٠٠ إلى ١٥٠ جنيه.', 'range_100_150'],
                        ['طبق أرز', 35, null],
                        ['طبق خضار', 35, null],
                        ['طبق شوربة كوارع سادة', 30, null],
                        ['طبق أرز بالكبدة', 70, null],
                        ['طبق كوارع وسط', 100, null],
                        ['طبق كوارع كبير', 140, null],
                        ['طبق كوارع بالأرز والخضار', 200, null],
                    ],
                ],
                [
                    'name' => 'المكرونات والطواجن', 'slug' => 'pasta-casseroles', 'description' => 'مكرونات وطواجن باللحوم.', 'photo' => 'macaroni',
                    'items' => [
                        ['مكرونة كبدة', 75, null],
                        ['مكرونة سجق', 75, null],
                        ['مكرونة كفتة', 75, null],
                        ['مكرونة مكس', 75, null],
                        ['طاجن مكرونة فرن بالموزاريلا (كبدة وسجق)', 90, null],
                    ],
                ],
                [
                    'name' => 'الحواوشي', 'slug' => 'hawawshi', 'description' => 'حواوشي السقا بإضافات متنوعة.', 'photo' => 'hawawshi',
                    'items' => [
                        ['حواوشي سادة', 30, null],
                        ['حواوشي ملفوف', 35, null],
                        ['حواوشي مكس السقا', 50, null],
                        ['حواوشي موزاريلا', 45, null],
                        ['حواوشي بالسجق', 60, null],
                        ['حواوشي جبنة كيري', 40, null],
                        ['حواوشي سجق بالموزاريلا', 75, null],
                    ],
                ],
                [
                    'name' => 'رغيف السقا', 'slug' => 'saqa-sandwiches', 'description' => 'سندوتشات كبدة وسجق ولحوم.', 'photo' => 'liver',
                    'items' => [
                        ['رغيف كبدة', 50, null],
                        ['رغيف سجق', 50, null],
                        ['رغيف كبدة وسجق', 50, null],
                        ['رغيف مشكل', 55, null],
                        ['رغيف كفتة', 50, null],
                        ['رغيف لحمة رأس', 55, null],
                        ['رغيف قلب وكلاوي', 50, null],
                        ['رغيف مخة وطحال', 55, null],
                        ['رغيف كبدة بالردة', 55, null],
                        ['رغيف كفتة وسجق', 55, null],
                        ['رغيف قلب', 55, null],
                        ['رغيف كلاوي', 50, null],
                        ['رغيف مخ', 70, null],
                        ['رغيف سجق موزاريلا', 65, null],
                        ['رغيف طحال', 60, null],
                        ['رغيف السقا', 70, 'كبدة + كفتة + لحمة + سجق.'],
                    ],
                ],
                [
                    'name' => 'المحمر باللية', 'slug' => 'mohammar', 'description' => 'أطباق ربع كيلو من قائمة المحمر باللية.', 'photo' => 'liver',
                    'items' => [
                        ['ربع كبدة بالردة', 140, null],
                        ['ربع كبدة محمرة', 125, null],
                        ['ربع كبدة إسكندراني', 140, null],
                        ['ربع كفتة محمرة', 125, null],
                        ['ربع سجق محمر', 125, null],
                        ['ربع لحمة رأس', 150, null],
                        ['ربع مشة وطحال', 150, null],
                        ['ربع مخ محمر', 150, null],
                        ['ربع مشكل فواكه اللحوم', 140, null],
                        ['ربع كبدة وسجق وكفتة', 140, null],
                        ['ربع كفتة وسجق', 140, null],
                        ['ربع قلب وكلاوي', 150, null],
                        ['ربع كبدة وسجق', 140, null],
                    ],
                ],
                [
                    'name' => 'جريل السقا', 'slug' => 'saqa-grill', 'description' => 'ورق جريل بالكبدة والسجق واللحوم.', 'photo' => 'grill',
                    'items' => [
                        ['ورقة كبدة', 150, null],
                        ['ورقة سجق', 150, null],
                        ['ورقة كفتة داود باشا', 150, null],
                        ['ورقة مكس', 160, null],
                        ['ورقة لحمة جريل', 200, null],
                    ],
                ],
                [
                    'name' => 'سلطات ومشروبات', 'slug' => 'salads-drinks', 'description' => 'سلطات جانبية ومشروبات.', 'photo' => 'salad',
                    'items' => [
                        ['سلطة السقا', 10, null],
                        ['سلطة خضراء', 10, null],
                        ['سلطة طحينة', 10, null],
                        ['سلطة بابا غنوج', 10, null],
                        ['طماطم مخللة', 10, null],
                        ['مياه معدنية', 20, null],
                        ['كنز', 25, null],
                    ],
                ],
            ];

            foreach ($sections as $categoryOrder => $section) {
                $category = MenuCategory::query()->withoutGlobalScopes()->updateOrCreate(
                    ['restaurant_id' => $restaurant->id, 'menu_id' => $menu->id, 'slug' => $section['slug']],
                    [
                        'name' => $section['name'],
                        'description' => $section['description'],
                        'sort_order' => $categoryOrder + 1,
                        'is_active' => true,
                    ]
                );

                foreach ($section['items'] as $itemOrder => $item) {
                    [$name, $price, $description] = $item;
                    $variantType = $item[3] ?? null;
                    $slug = $section['slug'].'-'.str_pad((string) ($itemOrder + 1), 3, '0', STR_PAD_LEFT);

                    $product = Product::query()->withoutGlobalScopes()->updateOrCreate(
                        ['restaurant_id' => $restaurant->id, 'menu_category_id' => $category->id, 'slug' => $slug],
                        [
                            'name' => $name,
                            'description' => $description,
                            'price' => $price,
                            'image_path' => $photos[$section['photo']],
                            'is_available' => true,
                            'is_featured' => $itemOrder === 0,
                            'sort_order' => $itemOrder + 1,
                        ]
                    );

                    if ($variantType === 'range_100_150') {
                        ProductVariant::query()->withoutGlobalScopes()->updateOrCreate(
                            ['restaurant_id' => $restaurant->id, 'product_id' => $product->id, 'name' => 'حجم صغير'],
                            ['price' => 100, 'price_delta' => 0, 'sort_order' => 1, 'is_active' => true]
                        );
                        ProductVariant::query()->withoutGlobalScopes()->updateOrCreate(
                            ['restaurant_id' => $restaurant->id, 'product_id' => $product->id, 'name' => 'حجم كبير'],
                            ['price' => 150, 'price_delta' => 0, 'sort_order' => 2, 'is_active' => true]
                        );
                    }
                }
            }

            $this->command?->info(sprintf(
                'Al Saqa menu is ready: restaurant #%d, menu #%d, %d categories, %d products.',
                $restaurant->id,
                $menu->id,
                count($sections),
                array_sum(array_map(fn (array $section): int => count($section['items']), $sections))
            ));
        });
    }
}
