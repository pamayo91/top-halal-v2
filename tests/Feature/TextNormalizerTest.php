<?php

namespace Tests\Feature;

use App\Services\TextNormalizer;
use Tests\TestCase;

class TextNormalizerTest extends TestCase
{
    public function test_valid_utf8_is_unchanged(): void { $this->assertSame('L’Étoile à Créteil', app(TextNormalizer::class)->plainText('L’Étoile à Créteil')); }
    public function test_html_entities_are_decoded_in_plain_text(): void { $this->assertSame("Adam's Burger & Grill", app(TextNormalizer::class)->plainText('Adam&amp;#039;s Burger &amp; Grill')); }
    public function test_known_mojibake_is_corrected_once(): void { $this->assertSame('M’A Pizza', app(TextNormalizer::class)->plainText('Mâ€™A Pizza')); }
    public function test_normalisation_is_idempotent(): void { $normalizer = app(TextNormalizer::class); $this->assertSame('M’A Pizza', $normalizer->plainText($normalizer->plainText('Mâ€™A Pizza'))); }
}
