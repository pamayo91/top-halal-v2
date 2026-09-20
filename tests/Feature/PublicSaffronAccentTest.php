<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicSaffronAccentTest extends TestCase
{
    public function test_saffron_tokens_are_restricted_to_decorative_accents_and_keep_green_focus(): void
    {
        $css = file_get_contents(resource_path('css/saffron.css'));
        $reviewCss = file_get_contents(resource_path('css/reviews.css'));
        $restaurantTemplate = file_get_contents(resource_path('views/public/restaurant.blade.php'));
        $editorialTemplate = file_get_contents(resource_path('views/public/editorial.blade.php'));
        $submissionTemplate = file_get_contents(resource_path('views/public/restaurant-submission/create.blade.php'));

        $this->assertStringContainsString('--color-saffron: #D9A441', $css);
        $this->assertStringContainsString('--color-saffron-soft: #F8F0DD', $css);
        $this->assertStringContainsString('.review-rating-input:focus-visible+.review-rating-star{outline:3px solid var(--green)', $reviewCss);
        $this->assertStringContainsString('class="rating-star"', $restaurantTemplate);
        $this->assertStringContainsString('accented-eyebrow', $restaurantTemplate);
        $this->assertStringContainsString('accented-eyebrow', $editorialTemplate);
        $this->assertStringContainsString('accented-eyebrow', $submissionTemplate);
        $this->assertStringContainsString('.badge-accent', $css);
        $this->assertStringContainsString('.prose blockquote', $css);
        $this->assertStringContainsString('::selection', $css);
        $this->assertStringNotContainsString('.button', $css);
    }
}
