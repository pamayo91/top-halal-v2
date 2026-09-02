<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Services\Geocoding\GeocodingService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class AutoGeocodeMissingGpsCommandTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(GeocodingService::class, new class implements GeocodingService {
            public function search(string $query, int $limit = 3): array {
                if (str_contains($query, 'Incompatible')) return ['ok'=>true, 'query'=>$query, 'features'=>[['type'=>'street', 'postcode'=>'75001', 'city'=>'Paris', 'citycode'=>'75056', 'latitude'=>48.86, 'longitude'=>2.35, 'id'=>'bad', 'score'=>0.4]], 'error'=>null, 'cached'=>false];
                return ['ok'=>true, 'query'=>$query, 'features'=>[['type'=>str_contains($query, 'Rue') ? 'street' : 'housenumber', 'postcode'=>'59777', 'city'=>'Lille', 'citycode'=>'59350', 'latitude'=>50.637, 'longitude'=>3.063, 'id'=>'ban-good', 'score'=>0.45]], 'error'=>null, 'cached'=>false];
            }
            public function reverse(float $latitude, float $longitude, int $limit = 3): array { return ['ok'=>true, 'query'=>'', 'features'=>[], 'error'=>null, 'cached'=>false]; }
        });
    }

    public function test_it_adds_fresh_burritos_style_house_number_gps_and_is_idempotent(): void
    {
        $restaurant = $this->restaurant(['name'=>'Fresh Burritos Euralille', 'address_line1'=>'100 Avenue Willy Brandt', 'postal_code'=>'59777', 'city_name'=>'Lille', 'city_code'=>'59350', 'country_code'=>'FR']);
        $this->artisan('data:autogeocode-missing-gps', ['--apply'=>true, '--out'=>'docs/generated/testing-missing-gps.md'])->assertSuccessful();
        $fresh = $restaurant->fresh();
        $this->assertSame('50.6370000', $fresh->latitude); $this->assertSame('3.0630000', $fresh->longitude);
        $before = $fresh->getAttributes();
        $this->artisan('data:autogeocode-missing-gps', ['--apply'=>true, '--out'=>'docs/generated/testing-missing-gps.md'])->assertSuccessful();
        $this->assertSame($before, $restaurant->fresh()->getAttributes());
    }

    public function test_it_accepts_matching_street_with_medium_score_but_rejects_an_incompatible_city(): void
    {
        $street = $this->restaurant(['name'=>'Rue compatible', 'address_line1'=>'Rue Test', 'postal_code'=>'59777', 'city_name'=>'Lille', 'city_code'=>'59350', 'country_code'=>'FR']);
        $bad = $this->restaurant(['name'=>'Incompatible', 'address_line1'=>'Incompatible', 'postal_code'=>'59777', 'city_name'=>'Lille', 'city_code'=>'59350', 'country_code'=>'FR']);
        $this->artisan('data:autogeocode-missing-gps', ['--apply'=>true, '--out'=>'docs/generated/testing-missing-gps.md'])->assertSuccessful();
        $this->assertSame('50.6370000', $street->fresh()->latitude); $this->assertSame('3.0630000', $street->fresh()->longitude);
        $this->assertNull($bad->fresh()->latitude); $this->assertNull($bad->fresh()->longitude);
    }

    public function test_it_never_replaces_existing_gps(): void
    {
        $restaurant = $this->restaurant(['address_line1'=>'100 Avenue Willy Brandt', 'postal_code'=>'59777', 'city_name'=>'Lille', 'city_code'=>'59350', 'latitude'=>48.0, 'longitude'=>2.0]);
        $this->artisan('data:autogeocode-missing-gps', ['--apply'=>true, '--out'=>'docs/generated/testing-missing-gps.md'])->assertSuccessful();
        $this->assertSame('48.0000000', $restaurant->fresh()->latitude); $this->assertSame('2.0000000', $restaurant->fresh()->longitude);
    }

    private function restaurant(array $attributes): Restaurant { return Restaurant::create($attributes + ['name'=>'GPS test '.str()->random(8), 'legacy_wp_id'=>random_int(1, 999999999), 'slug'=>'gps-'.str()->random(8), 'status'=>'published']); }
}
