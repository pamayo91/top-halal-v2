<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicTypographyTest extends TestCase
{
    public function test_public_typography_is_local_general_sans_without_a_remote_font_dependency(): void
    {
        $css = file_get_contents(resource_path('css/typography.css'));

        $this->assertFileExists(resource_path('fonts/general-sans/GeneralSans-Variable.woff2'));
        $this->assertFileExists(resource_path('fonts/general-sans/LICENSE.txt'));
        $this->assertStringContainsString("font-family: 'General Sans'", $css);
        $this->assertStringContainsString("url('../fonts/general-sans/GeneralSans-Variable.woff2')", $css);
        $this->assertStringContainsString('font-weight: 200 700', $css);
        $this->assertStringContainsString('font-display: swap', $css);
        $this->assertStringNotContainsString('fonts.googleapis.com', $css);
        $this->assertStringNotContainsString('fonts.gstatic.com', $css);
        $this->assertStringNotContainsString('fontshare.com', $css);
    }
}
