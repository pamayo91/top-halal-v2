<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Services\Geocoding\GeocodingService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ConsolidateAddressesCommandTest extends TestCase
{
    use DatabaseMigrations;

    public function test_it_completes_an_approximate_address_without_changing_raw_address_or_gps(): void
    {
        $restaurant = Restaurant::create([
            // Deliberately greater than the row count: batches must never use ID <= count().
            'id' => 9000,
            'legacy_wp_id' => 22801,
            'name' => 'O Sha',
            'slug' => 'o-sha',
            'status' => 'published',
            'address' => '46 Boulevard du Temple, Paris, France',
            'latitude' => '48.8659718',
            'longitude' => '2.3657509',
        ]);
        $before = $restaurant->only(['address', 'latitude', 'longitude']);

        app()->instance(GeocodingService::class, new class implements GeocodingService {
            public function search(string $query, int $limit = 3): array { return []; }
            public function reverse(float $latitude, float $longitude, int $limit = 3): array
            {
                return ['ok' => true, 'query' => '', 'cached' => true, 'error' => null, 'features' => [[
                    'label' => '48 Boulevard du Temple 75011 Paris',
                    'score' => 0.713846,
                    'type' => 'housenumber',
                    'id' => '75111_9190_00046',
                    'postcode' => '75011',
                    'city' => 'Paris',
                    'citycode' => '75111',
                ]]];
            }
        });

        $out = 'docs/generated/testing-address-consolidation.md';
        $this->artisan('data:consolidate-addresses', ['--apply' => true, '--ids' => $restaurant->id, '--out' => $out])->assertSuccessful();

        $restaurant->refresh();
        $this->assertSame($before, $restaurant->only(['address', 'latitude', 'longitude']));
        $this->assertSame('46 Boulevard du Temple', $restaurant->address_line1);
        $this->assertSame('75011', $restaurant->postal_code);
        $this->assertSame('Paris', $restaurant->city_name);
        $this->assertSame('75111', $restaurant->city_code);
        $this->assertSame('FR', $restaurant->country_code);
        File::delete(base_path($out));
    }
}
