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
}
