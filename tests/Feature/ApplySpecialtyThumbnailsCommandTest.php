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
}
