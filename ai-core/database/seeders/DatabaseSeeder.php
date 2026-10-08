<?php

namespace Database\Seeders;

use App\Models\Template;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
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
            }

            if ($data['key'] === 'fast-food') {
                $versionTwo = $template->versions()->updateOrCreate(
                    ['version' => 2],
                    ['schema' => [
                        'layout' => 'hero-categories-menu-detail',
                        'components' => ['sticky_header', 'hero', 'category_tiles', 'category_nav', 'product_list', 'cart_bar', 'product_sheet'],
                        'visual' => ['direction' => 'rtl', 'surface' => 'dark', 'accent' => 'gold', 'mobile_first' => true],
                    ], 'is_active' => true]
                );

                $versionTwo->activate();
            } else {
                $versionOne->activate();
            }
        }

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
