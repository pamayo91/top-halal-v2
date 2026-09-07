<?php

namespace Tests\Feature;

use App\Models\{MediaAsset, Restaurant, RestaurantMedia};
use App\Services\RestaurantMediaOrderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestaurantMediaOrdererTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_moves_only_regular_photos_and_keeps_fallback_thumbnails_out_of_the_gallery_order(): void
    {
        $restaurant = Restaurant::create(['legacy_wp_id' => 111, 'name' => 'Ordre média', 'slug' => 'ordre-media', 'status' => 'published']);
        $first = $this->media($restaurant, 0, 'gallery', 'a');
        $second = $this->media($restaurant, 1, 'gallery', 'b');
        $fallback = $this->media($restaurant, 0, 'fallback_thumbnail', 'c');

        $move = app(RestaurantMediaOrderer::class)->move($restaurant, $second->id, 'up');

        $this->assertSame(['from' => 1, 'to' => 0], $move);
        $this->assertSame([$second->id, $first->id], RestaurantMedia::query()->where('restaurant_id', $restaurant->id)->where('role', 'gallery')->orderBy('sort_order')->pluck('id')->all());
        $this->assertSame(0, $fallback->fresh()->sort_order);
    }

    public function test_it_does_not_move_a_cover_past_the_start_or_a_fallback_thumbnail(): void
    {
        $restaurant = Restaurant::create(['legacy_wp_id' => 112, 'name' => 'Ordre immobile', 'slug' => 'ordre-immobile', 'status' => 'published']);
        $first = $this->media($restaurant, 0, 'gallery', 'd');
        $fallback = $this->media($restaurant, 1, 'fallback_thumbnail', 'e');

        $this->assertNull(app(RestaurantMediaOrderer::class)->move($restaurant, $first->id, 'up'));
        $this->assertNull(app(RestaurantMediaOrderer::class)->move($restaurant, $fallback->id, 'up'));
        $this->assertSame(0, $first->fresh()->sort_order);
    }

    private function media(Restaurant $restaurant, int $sortOrder, string $role, string $checksumCharacter): RestaurantMedia
    {
        $asset = MediaAsset::create(['original_path' => "media/originals/{$checksumCharacter}.jpg", 'mime' => 'image/jpeg', 'width' => 800, 'height' => 600, 'bytes' => 10, 'checksum' => str_repeat($checksumCharacter, 64), 'status' => 'ready']);

        return RestaurantMedia::create(['restaurant_id' => $restaurant->id, 'media_asset_id' => $asset->id, 'sort_order' => $sortOrder, 'status' => 'ready', 'role' => $role]);
    }
}
