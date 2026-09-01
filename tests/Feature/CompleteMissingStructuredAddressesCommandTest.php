<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Services\Geocoding\GeocodingService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CompleteMissingStructuredAddressesCommandTest extends TestCase
{
    use DatabaseMigrations;

    public function test_it_completes_only_missing_structured_fields_from_geoplateforme_without_changing_raw_or_gps(): void
    {
        $restaurant = Restaurant::create(['legacy_wp_id' => 1, 'name' => 'Kebab Cansu', 'slug' => 'kebab-cansu', 'status' => 'published', 'address' => '16 rue Sainte Catherine 73220 Aiguebelle', 'latitude' => 45.0, 'longitude' => 6.0, 'geocoding_status' => 'REVIEW_REQUIRED']);
        $protected = $restaurant->only(['address', 'latitude', 'longitude', 'geocoding_status']);
        app()->instance(GeocodingService::class, new class implements GeocodingService {
            public function search(string $query, int $limit = 3): array { return ['ok' => true, 'cached' => true, 'features' => [['label' => '16 Rue Sainte-Catherine 73220 Aiguebelle', 'postcode' => '73220', 'city' => 'Aiguebelle', 'citycode' => '73002', 'id' => '73002_test', 'type' => 'housenumber', 'score' => .91]], 'error' => null, 'query' => '']; }
            public function reverse(float $latitude, float $longitude, int $limit = 3): array { return []; }
        });
        $out = 'docs/generated/testing-missing-structured-addresses.json';

        $this->artisan('data:complete-missing-structured-addresses', ['--apply' => true, '--expect' => 1, '--out' => $out])->assertSuccessful();
        $restaurant->refresh();
        $this->assertSame('16 Rue Sainte-Catherine', $restaurant->address_line1);
        $this->assertSame('73220', $restaurant->postal_code); $this->assertSame('Aiguebelle', $restaurant->city_name); $this->assertSame('73002', $restaurant->city_code); $this->assertSame('FR', $restaurant->country_code);
        $this->assertSame($protected, $restaurant->only(array_keys($protected)));
        $this->artisan('data:complete-missing-structured-addresses', ['--apply' => true, '--expect' => 0, '--out' => $out])->assertSuccessful();
        $this->assertSame(0, json_decode(File::get(base_path($out)), true)['stats']['corrected']);
        File::delete(base_path($out));
    }
}
