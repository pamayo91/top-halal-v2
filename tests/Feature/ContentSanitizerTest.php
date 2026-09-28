<?php

namespace Tests\Feature;

use App\Services\ContentSanitizer;
use Tests\TestCase;

class ContentSanitizerTest extends TestCase
{
    public function test_it_removes_absolute_and_relative_legacy_upload_images_by_default(): void
    {
        $html = '<img src="https://top-halal.fr/wp-content/uploads/a.jpg"><img src="/wp-contenu/uploads/b.jpg">';

        $result = app(ContentSanitizer::class)->sanitize($html);

        $this->assertSame('', $result['html']);
        $this->assertSame(['legacy_image', 'legacy_image'], $result['removed']);
    }

    public function test_it_can_preserve_legacy_images_for_the_inline_reconciliation_pass(): void
    {
        $html = '<img src="/wp-contenu/uploads/b.jpg">';

        $result = app(ContentSanitizer::class)->sanitize($html, false);

        $this->assertSame($html, $result['html']);
        $this->assertSame([], $result['removed']);
    }

    public function test_it_preserves_editorial_link_seo_and_target_attributes(): void
    {
        $html = '<p><a href="https://example.com" rel="nofollow">Nofollow</a> <a href="https://example.com" rel="sponsored">Sponsored</a> <a href="https://example.com" rel="ugc">UGC</a> <a href="https://example.com" rel="sponsored nofollow" target="_blank">Combined</a></p>';

        $result = app(ContentSanitizer::class)->sanitize($html);

        $this->assertSame($html, $result['html']);
        $this->assertSame([], $result['removed']);
    }

    public function test_it_preserves_supported_link_shapes_used_by_the_rich_editor(): void
    {
        $html = '<p>Voici un <a href="/devenir-auto-entrepreneur-restauration">lien <strong>interne</strong></a> et <a href="https://example.com">un lien externe</a>.</p>';

        $result = app(ContentSanitizer::class)->sanitize($html);

        $this->assertSame($html, $result['html']);
        $this->assertSame([], $result['removed']);
    }

    public function test_source_html_uses_the_same_dangerous_markup_rules_as_visual_editor_content(): void
    {
        $result = app(ContentSanitizer::class)->sanitize('<p>OK</p><script>alert(1)</script><a href="javascript:alert(1)" onclick="alert(1)">Lien</a>');

        $this->assertSame('<p>OK</p><a>Lien</a>', $result['html']);
        $this->assertSame(['script'], $result['removed']);
    }
}
