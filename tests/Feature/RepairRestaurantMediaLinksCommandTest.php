<?php

namespace Tests\Feature;

use App\Models\{MediaAsset, Restaurant};
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\{DB, File};
use Tests\TestCase;

class RepairRestaurantMediaLinksCommandTest extends TestCase
{
    use DatabaseMigrations;

    private string $report = 'storage/app/private/restaurant-media-link-repair-test';

    protected function tearDown(): void
    {
        File::delete(base_path($this->report.'.json'), base_path($this->report.'.md'));

        parent::tearDown();
    }

    public function test_it_dry_runs_then_repairs_only_verified_v2_links_idempotently(): void
    {
        $restaurant = $this->restaurant();
        $asset = $this->asset(12345, 'a');
        $relationId = $this->unlinkedRelation($restaurant->id, $asset->legacy_attachment_id);

        $this->artisan('data:repair-restaurant-media-links', ['--out' => $this->report])
            ->expectsOutput('Candidates: 1; repaired: 0; conflicts: 0.')
            ->assertSuccessful();
        $this->assertDatabaseHas('restaurant_media', ['id' => $relationId, 'media_asset_id' => null]);

        $this->artisan('data:repair-restaurant-media-links', ['--apply' => true, '--out' => $this->report])
            ->expectsOutput('Candidates: 1; repaired: 1; conflicts: 0.')
            ->assertSuccessful();
        $this->assertDatabaseHas('restaurant_media', ['id' => $relationId, 'media_asset_id' => $asset->id]);

        $this->artisan('data:repair-restaurant-media-links', ['--apply' => true, '--out' => $this->report])
            ->expectsOutput('Candidates: 0; repaired: 0; conflicts: 0.')
            ->assertSuccessful();
        $this->assertStringContainsString('"mode": "apply"', File::get(base_path($this->report.'.json')));
    }

    public function test_it_preserves_a_conflicting_existing_direct_v2_link(): void
    {
        $restaurant = $this->restaurant();
        $asset = $this->asset(54321, 'b');
        $candidateId = $this->unlinkedRelation($restaurant->id, $asset->legacy_attachment_id);
        DB::table('restaurant_media')->insert([
            'restaurant_id' => $restaurant->id,
            'legacy_attachment_id' => null,
            'media_asset_id' => $asset->id,
            'sort_order' => 1,
            'status' => 'ready',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('data:repair-restaurant-media-links', ['--apply' => true, '--out' => $this->report])
            ->expectsOutput('Candidates: 1; repaired: 0; conflicts: 1.')
            ->assertSuccessful();

        $this->assertDatabaseHas('restaurant_media', ['id' => $candidateId, 'media_asset_id' => null]);
    }

    private function restaurant(): Restaurant
    {
        return Restaurant::create([
            'legacy_wp_id' => random_int(1, 999999999),
            'name' => 'Restaurant média',
            'slug' => 'restaurant-media-'.str()->random(10),
            'status' => 'published',
        ]);
    }

    private function asset(int $legacyAttachmentId, string $checksumCharacter): MediaAsset
    {
        return MediaAsset::create([
            'legacy_attachment_id' => $legacyAttachmentId,
            'original_path' => 'media/originals/'.$legacyAttachmentId.'.jpg',
            'mime' => 'image/jpeg',
            'width' => 1200,
            'height' => 800,
            'bytes' => 100,
            'checksum' => str_repeat($checksumCharacter, 64),
            'status' => 'ready',
        ]);
    }

    private function unlinkedRelation(int $restaurantId, int $legacyAttachmentId): int
    {
        return (int) DB::table('restaurant_media')->insertGetId([
            'restaurant_id' => $restaurantId,
            'legacy_attachment_id' => $legacyAttachmentId,
            'media_asset_id' => null,
            'sort_order' => 0,
            'status' => 'ready',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
