<?php

namespace Tests\Feature;

use App\Models\Template;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CafeTemplateContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_cafe_template_seeds_a_v2_production_schema_as_active(): void
    {
        $this->seed(DatabaseSeeder::class);

        $template = Template::query()->where('key', 'cafe')->firstOrFail();
        $active = $template->activeVersion()->firstOrFail();

        $this->assertSame(2, $active->version);
        $this->assertSame('hero-categories-menu-detail', $active->schema['layout']);
        $this->assertContains('hero', $active->schema['components']);
        $this->assertContains('product_sheet', $active->schema['components']);
        $this->assertSame('coffee-gold', $active->schema['visual']['accent']);
    }

    public function test_cafe_v1_is_preserved_as_historical_version(): void
    {
        $this->seed(DatabaseSeeder::class);

        $template = Template::query()->where('key', 'cafe')->firstOrFail();

        $this->assertFalse($template->versions()->where('version', 1)->firstOrFail()->is_active);
        $this->assertTrue($template->versions()->where('version', 2)->firstOrFail()->is_active);
    }
}
