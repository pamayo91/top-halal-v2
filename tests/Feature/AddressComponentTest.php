<?php

namespace Tests\Feature;

use App\Models\{Restaurant, User};
use App\Services\Geocoding\GeocodingService;
use App\Services\Location\{AddressSuggestionService, DuplicateRestaurantDetector, RestaurantLocationService};
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AddressComponentTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(GeocodingService::class, new class implements GeocodingService {
            public function search(string $query, int $limit = 3): array { return ['ok'=>true, 'query'=>$query, 'cached'=>false, 'error'=>null, 'features'=>[['label'=>'46 Boulevard du Temple 75011 Paris', 'postcode'=>'75011', 'city'=>'Paris', 'citycode'=>'75111', 'latitude'=>48.866, 'longitude'=>2.364, 'id'=>'BAN-46', 'type'=>'housenumber', 'score'=>0.92]]]; }
            public function reverse(float $latitude, float $longitude, int $limit = 3): array { return ['ok'=>true, 'query'=>'', 'cached'=>false, 'error'=>null, 'features'=>[]]; }
        });
    }

    public function test_autocomplete_only_returns_complete_addresses_with_valid_gps(): void
    {
        $service = app(AddressSuggestionService::class);
        $this->assertSame([], $service->suggest('ab'));
        $suggestion = $service->suggest('46 boulevard du temple')[0];
        $structured = $service->structured($service->resolve($suggestion['token']));
        $this->assertSame('75111', $structured['city_code']);
        $this->assertSame(48.866, $structured['latitude']);
        $this->assertSame(2.364, $structured['longitude']);
    }

    public function test_autocomplete_endpoint_is_admin_only_and_uses_a_safe_contract(): void
    {
        $this->getJson(route('admin.location.autocomplete', ['q' => 'Paris']))->assertUnauthorized();
        $admin = User::factory()->create(['role'=>'admin']);
        $this->actingAs($admin)->getJson(route('admin.location.autocomplete', ['q' => 'Paris']))->assertOk()->assertJsonPath('data.0.label', '46 Boulevard du Temple 75011 Paris');
        $this->actingAs($admin)->getJson(route('admin.location.autocomplete', ['q' => 'ab']))->assertUnprocessable();
    }

    public function test_selected_address_preserves_the_historical_address_and_is_audited(): void
    {
        $admin = User::factory()->create(['role'=>'admin']); $this->actingAs($admin);
        $restaurant = $this->restaurant(['address'=>'46 Boulevard du Temple, Paris, France']);
        $feature = app(AddressSuggestionService::class)->suggest('46 boulevard')[0]['feature'];

        app(RestaurantLocationService::class)->update($restaurant, [...app(AddressSuggestionService::class)->structured($feature), 'location_update_source'=>'autocomplete']);

        $fresh = $restaurant->fresh();
        $this->assertSame('46 Boulevard du Temple, Paris, France', $fresh->address);
        $this->assertSame('46 Boulevard du Temple', $fresh->address_line1);
        $this->assertSame('75111', $fresh->city_code);
        $this->assertDatabaseHas('admin_audit_logs', ['action'=>'restaurant.location_updated', 'subject_id'=>$restaurant->id]);
    }

    public function test_marker_move_changes_only_coordinates(): void
    {
        $restaurant = $this->restaurant(['address'=>'Adresse historique', 'address_line1'=>'46 Boulevard du Temple', 'postal_code'=>'75011', 'city_name'=>'Paris', 'city_code'=>'75111', 'country_code'=>'FR', 'latitude'=>48.866, 'longitude'=>2.364]);
        $before = $restaurant->getAttributes();

        app(RestaurantLocationService::class)->update($restaurant, ['latitude'=>48.867, 'longitude'=>2.365, 'location_update_source'=>'public_map']);

        $fresh = $restaurant->fresh();
        foreach ($before as $field => $value) {
            if (in_array($field, ['latitude', 'longitude', 'updated_at'], true)) continue;
            $this->assertSame($value, $fresh->getAttribute($field), "{$field} must not change when moving the marker.");
        }
        $this->assertSame('48.8670000', $fresh->latitude);
        $this->assertSame('2.3650000', $fresh->longitude);
    }

    public function test_duplicate_detector_is_informative_not_an_automatic_merge(): void
    {
        $first = $this->restaurant(['name'=>'O Sha', 'latitude'=>48.866, 'longitude'=>2.364, 'address_line1'=>'46 Boulevard du Temple']);
        $second = $this->restaurant(['name'=>'O Sha', 'latitude'=>48.8661, 'longitude'=>2.3641, 'address_line1'=>'46 Boulevard du Temple']);
        $this->assertCount(1, app(DuplicateRestaurantDetector::class)->candidates($first));
        $this->assertDatabaseHas('restaurants', ['id'=>$second->id]);
    }

    public function test_legacy_qualification_columns_are_absent_from_the_restaurant_schema(): void
    {
        $columns = Schema::getColumnListing('restaurants');
        foreach (['location_review_reason', 'location_precision', 'address_confidence', 'geocoded_at', 'geocoding_review_reason', 'geocoding_score', 'geocoding_status', 'geocoding_precision', 'geocoding_source_id', 'geocoding_provider', 'geocoding_distance_m', 'proximity_status'] as $column) {
            $this->assertNotContains($column, $columns);
        }
        $this->assertContains('address', $columns);
    }

    private function restaurant(array $attributes = []): Restaurant
    {
        return Restaurant::create($attributes + ['legacy_wp_id'=>random_int(1, 999999999), 'name'=>'Restaurant '.str()->random(8), 'slug'=>'restaurant-'.str()->random(8), 'status'=>'published']);
    }
}
