<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class RepairAddressLine1CommandTest extends TestCase
{
    use DatabaseMigrations;

    public function test_it_only_updates_address_line1_and_is_idempotent(): void
    {
        $restaurant = Restaurant::create([
            'legacy_wp_id' => 17128,
            'name' => 'Abdallah Suleiman',
            'slug' => 'abdallah-suleiman',
            'status' => 'published',
            'address' => '54 Boulevard de La libération 13001 Marseille',
            'address_line1' => '54 Boulevard de La libération 13001 Marseille',
            'address_line2' => 'Bâtiment A',
            'postal_code' => '13001',
            'city_name' => 'Marseille',
            'city_code' => '13201',
            'country_code' => 'FR',
            'latitude' => '43.2997549',
            'longitude' => '5.3876106',
            'geocoding_status' => 'REVIEW_REQUIRED',
            'geocoding_review_reason' => 'résolu automatiquement',
            'address_confidence' => 'APPROXIMATE',
            'location_precision' => 'HOUSENUMBER',
            'proximity_status' => 'ELIGIBLE',
        ]);
        $protected = $restaurant->only([
            'address', 'address_line2', 'postal_code', 'city_name', 'city_code', 'country_code',
            'latitude', 'longitude', 'geocoding_status', 'geocoding_review_reason',
            'address_confidence', 'location_precision', 'proximity_status',
        ]);
        $out = 'docs/generated/testing-address-line1-repair.json';

        $this->artisan('data:repair-address-line1', ['--apply' => true, '--expect' => 1, '--out' => $out])->assertSuccessful();
        $restaurant->refresh();
        $this->assertSame('54 Boulevard de La libération', $restaurant->address_line1);
        $this->assertSame($protected, $restaurant->only(array_keys($protected)));

        $this->artisan('data:repair-address-line1', ['--apply' => true, '--expect' => 0, '--out' => $out])->assertSuccessful();
        $report = json_decode(File::get(base_path($out)), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(0, $report['stats']['corrected']);
        File::delete(base_path($out));
    }

    public function test_it_keeps_ambiguous_rows_unchanged(): void
    {
        $restaurant = Restaurant::create([
            'legacy_wp_id' => 1, 'name' => 'Mismatch', 'slug' => 'mismatch', 'status' => 'published',
            'address' => '1 Place de la Mairie 13100 Aix-en-Provence',
            'address_line1' => '1 Place de la Mairie 13100 Aix-en-Provence',
            'postal_code' => '13290', 'city_name' => 'Aix-en-Provence',
        ]);
        $out = 'docs/generated/testing-address-line1-ambiguous.json';

        $this->artisan('data:repair-address-line1', ['--out' => $out])->assertSuccessful();
        $restaurant->refresh();
        $this->assertSame('1 Place de la Mairie 13100 Aix-en-Provence', $restaurant->address_line1);
        $report = json_decode(File::get(base_path($out)), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(1, $report['stats']['ambiguous']);
        $this->assertSame([], $report['candidates']);
        File::delete(base_path($out));
    }
}
