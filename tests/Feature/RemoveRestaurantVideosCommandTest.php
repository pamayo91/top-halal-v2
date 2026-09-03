<?php

namespace Tests\Feature;

use App\Models\{ContentMedia, MediaAsset, Restaurant, RestaurantMedia};
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\{File, Storage};
use Tests\TestCase;

class RemoveRestaurantVideosCommandTest extends TestCase
{
    use DatabaseMigrations;

    private string $report = 'storage/app/private/restaurant-video-removal-test';

    protected function setUp(): void
    {
        parent::setUp();
        config(['legacy-media.disk' => 'restaurant-video-removal-test']);
        Storage::fake('restaurant-video-removal-test');
    }

    protected function tearDown(): void
    {
        File::delete(base_path($this->report.'.json'), base_path($this->report.'.md'));

        parent::tearDown();
    }

    public function test_it_dry_runs_then_removes_restaurant_video_relations_promotes_an_image_and_purges_only_orphans(): void
    {
        $restaurant = $this->restaurant();
        $video = $this->asset('video/mp4', 'video.mp4', 'a');
        $image = $this->asset('image/jpeg', 'image.jpg', 'b', 1200, 800);
        Storage::disk('restaurant-video-removal-test')->put($video->original_path, 'video');
        Storage::disk('restaurant-video-removal-test')->put($image->original_path, 'image');
        RestaurantMedia::create(['restaurant_id' => $restaurant->id, 'media_asset_id' => $video->id, 'sort_order' => 0, 'status' => 'ready']);
        RestaurantMedia::create(['restaurant_id' => $restaurant->id, 'media_asset_id' => $image->id, 'sort_order' => 1, 'status' => 'ready']);

        $this->artisan('data:remove-restaurant-videos', ['--out' => $this->report])
            ->expectsOutput('Video relations: 1; removed: 0; orphaned assets purged: 0; restaurants without an image: 0.')
            ->assertSuccessful();
        $this->assertDatabaseHas('restaurant_media', ['restaurant_id' => $restaurant->id, 'media_asset_id' => $video->id]);

        $this->artisan('data:remove-restaurant-videos', ['--apply' => true, '--purge-orphaned-assets' => true, '--out' => $this->report])
            ->expectsOutput('Video relations: 1; removed: 1; orphaned assets purged: 1; restaurants without an image: 0.')
            ->assertSuccessful();

        $this->assertDatabaseMissing('restaurant_media', ['restaurant_id' => $restaurant->id, 'media_asset_id' => $video->id]);
        $this->assertDatabaseHas('restaurant_media', ['restaurant_id' => $restaurant->id, 'media_asset_id' => $image->id, 'sort_order' => 0]);
        $this->assertDatabaseMissing('media_assets', ['id' => $video->id]);
        Storage::disk('restaurant-video-removal-test')->assertMissing($video->original_path);

        $this->artisan('data:remove-restaurant-videos', ['--apply' => true, '--purge-orphaned-assets' => true, '--out' => $this->report])
            ->expectsOutput('Video relations: 0; removed: 0; orphaned assets purged: 0; restaurants without an image: 0.')
            ->assertSuccessful();
    }

    public function test_it_retains_a_video_asset_referenced_by_editorial_content(): void
    {
        $restaurant = $this->restaurant();
        $video = $this->asset('video/mp4', 'shared.mp4', 'c');
        RestaurantMedia::create(['restaurant_id' => $restaurant->id, 'media_asset_id' => $video->id, 'sort_order' => 0, 'status' => 'ready']);
        ContentMedia::create(['content_type' => 'post', 'content_id' => 1, 'legacy_attachment_id' => 999999, 'media_asset_id' => $video->id, 'role' => 'inline']);

        $this->artisan('data:remove-restaurant-videos', ['--apply' => true, '--purge-orphaned-assets' => true, '--out' => $this->report])->assertSuccessful();

        $this->assertDatabaseMissing('restaurant_media', ['restaurant_id' => $restaurant->id, 'media_asset_id' => $video->id]);
        $this->assertDatabaseHas('media_assets', ['id' => $video->id]);
    }

    private function restaurant(): Restaurant
    {
        return Restaurant::create(['legacy_wp_id' => random_int(1, 999999999), 'name' => 'Restaurant vidéo', 'slug' => 'restaurant-video-'.str()->random(10), 'status' => 'published']);
    }

    private function asset(string $mime, string $path, string $checksumCharacter, ?int $width = null, ?int $height = null): MediaAsset
    {
        return MediaAsset::create(['original_path' => 'media/originals/'.$path, 'mime' => $mime, 'width' => $width, 'height' => $height, 'bytes' => 10, 'checksum' => str_repeat($checksumCharacter, 64), 'status' => 'ready']);
    }
}
