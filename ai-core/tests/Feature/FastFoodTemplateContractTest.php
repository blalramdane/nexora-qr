<?php

namespace Tests\Feature;

use App\Models\Template;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FastFoodTemplateContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_fast_food_template_seeds_a_v2_production_schema_as_active(): void
    {
        $this->seed(DatabaseSeeder::class);

        $template = Template::query()->where('key', 'fast-food')->firstOrFail();
        $active = $template->activeVersion()->firstOrFail();

        $this->assertSame(2, $active->version);
        $this->assertSame('hero-categories-menu-detail', $active->schema['layout']);
        $this->assertContains('hero', $active->schema['components']);
        $this->assertContains('product_sheet', $active->schema['components']);
        $this->assertSame('dark', $active->schema['visual']['surface']);
        $this->assertSame('gold', $active->schema['visual']['accent']);
    }

    public function test_fast_food_v1_remains_historical_after_v2_activation(): void
    {
        $this->seed(DatabaseSeeder::class);

        $template = Template::query()->where('key', 'fast-food')->firstOrFail();
        $v1 = $template->versions()->where('version', 1)->firstOrFail();
        $v2 = $template->versions()->where('version', 2)->firstOrFail();

        $this->assertFalse($v1->is_active);
        $this->assertTrue($v2->is_active);
        $this->assertSame('header', $v1->schema['components'][0]);
    }
}
