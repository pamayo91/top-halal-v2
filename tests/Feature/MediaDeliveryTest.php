<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaDeliveryTest extends TestCase
{
    use DatabaseMigrations;

    public function test_it_serves_a_copied_original_with_safe_cache_headers(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('media/originals/example.jpg', 'jpeg bytes');
        $asset = MediaAsset::create([
            'legacy_attachment_id' => 42, 'original_path' => 'media/originals/example.jpg', 'mime' => 'image/jpeg',
            'width' => 1200, 'height' => 800, 'bytes' => 10, 'checksum' => str_repeat('a', 64), 'status' => 'ready',
        ]);

        $response = $this->get(route('media.show', $asset))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertStringContainsString('public', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=31536000', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('immutable', (string) $response->headers->get('Cache-Control'));
    }

    public function test_it_serves_only_an_existing_requested_webp_variant(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('media/originals/example.jpg', 'jpeg bytes');
        Storage::disk('local')->put('media/variants/example-480.webp', 'webp bytes');
        $asset = MediaAsset::create([
            'legacy_attachment_id' => 43, 'original_path' => 'media/originals/example.jpg', 'mime' => 'image/jpeg',
            'width' => 1200, 'height' => 800, 'bytes' => 10, 'checksum' => str_repeat('b', 64), 'status' => 'ready',
        ]);
        $asset->variants()->create(['format' => 'webp', 'width' => 480, 'height' => 320, 'path' => 'media/variants/example-480.webp']);

        $this->get(route('media.show', [$asset, 480]))->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->get(route('media.show', [$asset, 960]))->assertNotFound();
    }
}
