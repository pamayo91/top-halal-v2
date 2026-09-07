<?php

namespace Tests\Feature;

use App\Models\{Restaurant, RestaurantMedia};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplyQuickCoverMediaCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_normalizes_and_assigns_a_standard_cover_relation_idempotently(): void
    {
        $restaurant = Restaurant::factory()->create(['slug' => 'quick-test', 'legacy_wp_id' => null]);
        $source = storage_path('app/private/test-quick-logo.png'); $output = storage_path('app/private/test-quick-logo.webp');
        @mkdir(dirname($source), 0777, true); $image = imagecreatetruecolor(1600, 1200); imagepng($image, $source); imagedestroy($image);
        $arguments = ['--apply' => true, '--source' => $source, '--output' => $output, '--report' => 'docs/generated/test-quick-cover.md'];
        $this->artisan('restaurants:apply-quick-cover', $arguments)->assertSuccessful();
        $media = RestaurantMedia::where('restaurant_id', $restaurant->id)->firstOrFail();
        $this->assertSame('gallery', $media->role); $this->assertSame(0, $media->sort_order); $this->assertSame('image/webp', $media->asset->mime); $this->assertSame(1440, $media->asset->width); $this->assertSame(1080, $media->asset->height); $this->assertCount(3, $media->asset->variants);
        $this->artisan('restaurants:apply-quick-cover', $arguments)->assertSuccessful();
        $this->assertSame(1, RestaurantMedia::where('restaurant_id', $restaurant->id)->count());
    }
}
