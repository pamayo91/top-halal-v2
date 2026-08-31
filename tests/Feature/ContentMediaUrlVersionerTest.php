<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Services\ContentMediaUrlVersioner;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class ContentMediaUrlVersionerTest extends TestCase
{
    use DatabaseMigrations;

    public function test_it_rewrites_originals_and_variants_without_touching_unknown_or_versioned_urls(): void
    {
        $asset = MediaAsset::create(['legacy_attachment_id' => 44, 'original_path' => 'media/originals/example.jpg', 'mime' => 'image/jpeg', 'width' => 800, 'height' => 600, 'bytes' => 10, 'checksum' => str_repeat('a', 64), 'status' => 'ready']);
        $html = '<img src="/media/'.$asset->id.'" srcset="/media/'.$asset->id.'/480 480w"><a href="/media/999999">Télécharger</a><img src="/media/'.$asset->id.'/v/'.str_repeat('a', 64).'">';

        $result = app(ContentMediaUrlVersioner::class)->rewrite($html);

        $this->assertSame(2, $result['replaced']);
        $this->assertSame([999999], $result['unresolved_asset_ids']);
        $this->assertStringContainsString(parse_url($asset->deliveryUrl(), PHP_URL_PATH), $result['html']);
        $this->assertStringContainsString(parse_url($asset->deliveryUrl(480), PHP_URL_PATH).' 480w', $result['html']);
        $this->assertStringContainsString('/media/999999', $result['html']);
        $this->assertStringContainsString('/media/'.$asset->id.'/v/'.str_repeat('a', 64), $result['html']);
    }
}
