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
            $template->versions()->updateOrCreate(
                ['version' => 1],
                ['schema' => ['components' => ['header', 'category_nav', 'product_card', 'cart_bar'], 'variants' => ['compact', 'editorial', 'featured']], 'is_active' => true]
            );
        }

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
