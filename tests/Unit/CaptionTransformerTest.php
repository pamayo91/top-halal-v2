<?php

namespace Tests\Unit;

use App\Services\ContentTransformer;
use PHPUnit\Framework\TestCase;

class CaptionTransformerTest extends TestCase
{
    public function test_it_converts_wordpress_caption_shortcodes_to_figure_markup(): void
    {
        $html = '[caption id="attachment_2389" align="aligncenter" width="225"]<img src="/image.jpg" width="225" height="300"> pitas au fromage[/caption]';
        $result = (new ContentTransformer)->transform($html)['html'];
        $this->assertStringContainsString('<figure class="legacy-caption">', $result);
        $this->assertStringContainsString('<figcaption>pitas au fromage</figcaption>', $result);
        $this->assertStringNotContainsString('[caption', $result);
        $this->assertStringNotContainsString('[/caption]', $result);
    }
}
