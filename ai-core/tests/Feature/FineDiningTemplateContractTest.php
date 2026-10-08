<?php

namespace Tests\Feature;

use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FineDiningTemplateContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_fine_dining_supports_a_production_v2_schema(): void
    {
        $template = Template::create([
            'key' => 'fine-dining',
            'name' => 'Fine Dining',
            'is_premium' => true,
        ]);

        $v1 = $template->createVersion([
            'components' => ['header', 'category_nav', 'product_card', 'cart_bar'],
        ], true);

        $v2 = $template->createVersion([
            'layout' => 'editorial-hero-curated-menu-detail',
            'components' => ['minimal_header', 'hero', 'story_strip', 'category_nav', 'editorial_product_list', 'cart_bar', 'product_sheet'],
            'visual' => ['direction' => 'rtl', 'surface' => 'dark', 'accent' => 'champagne-gold', 'mobile_first' => true, 'typography' => 'serif'],
        ]);

        $v2->activate();

        $active = $template->fresh()->activeVersion()->firstOrFail();

        $this->assertSame(2, $active->version);
        $this->assertSame('editorial-hero-curated-menu-detail', $active->schema['layout']);
        $this->assertContains('story_strip', $active->schema['components']);
        $this->assertContains('product_sheet', $active->schema['components']);
        $this->assertSame('champagne-gold', $active->schema['visual']['accent']);
        $this->assertSame('serif', $active->schema['visual']['typography']);
        $this->assertFalse($v1->fresh()->is_active);
        $this->assertTrue($v2->fresh()->is_active);
    }
}
