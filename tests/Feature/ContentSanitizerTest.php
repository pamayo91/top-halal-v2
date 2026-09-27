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
}
