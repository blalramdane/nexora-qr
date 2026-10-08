<?php

namespace Tests\Feature;

use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CafeTemplateContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_cafe_template_supports_a_production_v2_schema(): void
    {
        $template = Template::create([
            'key' => 'cafe',
            'name' => 'Café',
        ]);

        $v1 = $template->createVersion([
            'components' => ['header', 'category_nav', 'product_card', 'cart_bar'],
        ], true);

        $v2 = $template->createVersion([
            'layout' => 'hero-categories-menu-detail',
            'components' => ['sticky_header', 'hero', 'category_tiles', 'category_nav', 'product_list', 'cart_bar', 'product_sheet'],
            'visual' => ['direction' => 'rtl', 'surface' => 'dark', 'accent' => 'coffee-gold', 'mobile_first' => true],
        ]);

        $v2->activate();

        $active = $template->fresh()->activeVersion()->firstOrFail();

        $this->assertSame(2, $active->version);
        $this->assertSame('hero-categories-menu-detail', $active->schema['layout']);
        $this->assertContains('hero', $active->schema['components']);
        $this->assertContains('product_sheet', $active->schema['components']);
        $this->assertSame('coffee-gold', $active->schema['visual']['accent']);
        $this->assertFalse($v1->fresh()->is_active);
        $this->assertTrue($v2->fresh()->is_active);
    }
}
