<?php

namespace Tests\Feature;

use App\Models\{Category, Restaurant};
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\{File, Storage};
use Tests\TestCase;

class ApplySpecialtyThumbnailsCommandTest extends TestCase
{
    use DatabaseMigrations;

    public function test_it_creates_a_card_only_specialty_thumbnail_for_a_restaurant_without_a_photo(): void
    {
        Storage::fake('local');
        $source = storage_path('framework/testing/specialty-source');
        $output = storage_path('framework/testing/specialty-output');
        $report = storage_path('framework/testing/specialty-report.json');
        File::deleteDirectory($source);
        File::deleteDirectory($output);
        File::delete($report);
        File::ensureDirectoryExists($source);
        $image = imagecreatetruecolor(1600, 1000);
        imagejpeg($image, $source.'/burger.jpg', 90);
        imagedestroy($image);

        $burger = Category::where('slug', 'burger')->firstOrFail();
        $restaurant = Restaurant::create(['legacy_wp_id' => 700001, 'name' => 'Sans photo', 'slug' => 'sans-photo', 'status' => 'published']);
        $restaurant->categories()->attach($burger);

        $this->artisan('data:apply-specialty-thumbnails', ['--apply' => true, '--slugs' => 'burger', '--source' => $source, '--output' => $output, '--report' => $report])
            ->assertExitCode(0);

        $burger->refresh();
        $this->assertNotNull($burger->media_asset_id);
        $this->assertSame(1200, $burger->media->width);
        $this->assertSame(800, $burger->media->height);
        $this->assertFileExists($output.'/burger.webp');
        $this->assertDatabaseHas('restaurant_media', ['restaurant_id' => $restaurant->id, 'media_asset_id' => $burger->media_asset_id, 'role' => 'fallback_thumbnail']);
        $this->assertFileExists($report);
    }

    public function test_it_replaces_an_outdated_specialty_thumbnail_without_creating_a_duplicate(): void
    {
        Storage::fake('local');
        $source = storage_path('framework/testing/specialty-replacement-source');
        $output = storage_path('framework/testing/specialty-replacement-output');
        $report = storage_path('framework/testing/specialty-replacement-report.json');
        File::deleteDirectory($source);
        File::deleteDirectory($output);
        File::delete($report);
        File::ensureDirectoryExists($source);
        $image = imagecreatetruecolor(1600, 1000);
        imagejpeg($image, $source.'/burger.jpg', 90);
        imagedestroy($image);

        $burger = Category::where('slug', 'burger')->firstOrFail();
        $restaurant = Restaurant::create(['legacy_wp_id' => 700002, 'name' => 'Miniature à remplacer', 'slug' => 'miniature-a-remplacer', 'status' => 'published']);
        $restaurant->categories()->attach($burger);
        $old = \App\Models\MediaAsset::create(['original_path' => 'media/originals/old.webp', 'mime' => 'image/webp', 'width' => 1200, 'height' => 800, 'bytes' => 1, 'checksum' => str_repeat('b', 64)]);
        \App\Models\RestaurantMedia::create(['restaurant_id' => $restaurant->id, 'media_asset_id' => $old->id, 'sort_order' => 0, 'status' => 'ready', 'role' => 'fallback_thumbnail']);

        $this->artisan('data:apply-specialty-thumbnails', ['--apply' => true, '--slugs' => 'burger', '--source' => $source, '--output' => $output, '--report' => $report])
            ->assertExitCode(0);

        $burger->refresh();
        $this->assertDatabaseMissing('restaurant_media', ['restaurant_id' => $restaurant->id, 'media_asset_id' => $old->id]);
        $this->assertDatabaseCount('restaurant_media', 1);
        $this->assertDatabaseHas('restaurant_media', ['restaurant_id' => $restaurant->id, 'media_asset_id' => $burger->media_asset_id, 'role' => 'fallback_thumbnail']);
    }

    public function test_a_specialty_subset_does_not_touch_other_restaurants(): void
    {
        Storage::fake('local');
        $source = storage_path('framework/testing/specialty-subset-source');
        $output = storage_path('framework/testing/specialty-subset-output');
        $report = storage_path('framework/testing/specialty-subset-report.json');
        File::deleteDirectory($source);
        File::deleteDirectory($output);
        File::delete($report);
        File::ensureDirectoryExists($source);
        $image = imagecreatetruecolor(1600, 1000);
        imagejpeg($image, $source.'/burger.jpg', 90);
        imagedestroy($image);

        $burger = Category::where('slug', 'burger')->firstOrFail();
        $other = Category::firstOrCreate(['slug' => 'cuisine-francaise'], ['legacy_term_id' => 700004, 'name' => 'Française']);
        $restaurant = Restaurant::create(['legacy_wp_id' => 700003, 'name' => 'Autre spécialité', 'slug' => 'autre-specialite', 'status' => 'published']);
        $restaurant->categories()->attach($other);
        $old = \App\Models\MediaAsset::create(['original_path' => 'media/originals/other.webp', 'mime' => 'image/webp', 'width' => 1200, 'height' => 800, 'bytes' => 1, 'checksum' => str_repeat('c', 64)]);
        \App\Models\RestaurantMedia::create(['restaurant_id' => $restaurant->id, 'media_asset_id' => $old->id, 'sort_order' => 0, 'status' => 'ready', 'role' => 'fallback_thumbnail']);

        $this->artisan('data:apply-specialty-thumbnails', ['--apply' => true, '--slugs' => $burger->slug, '--source' => $source, '--output' => $output, '--report' => $report])
            ->assertExitCode(0);

        $this->assertDatabaseHas('restaurant_media', ['restaurant_id' => $restaurant->id, 'media_asset_id' => $old->id, 'role' => 'fallback_thumbnail']);
    }
}
