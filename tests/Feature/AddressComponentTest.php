<?php

namespace Tests\Feature;

use App\Models\{AdminAuditLog, Restaurant, User};
use App\Services\Geocoding\GeocodingService;
use App\Services\Location\{AddressSuggestionService, DuplicateRestaurantDetector, RestaurantLocationService};
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class AddressComponentTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(GeocodingService::class, new class implements GeocodingService {
            public function search(string $query, int $limit = 3): array { return ['ok'=>true, 'query'=>$query, 'cached'=>false, 'error'=>null, 'features'=>[['label'=>'46 Boulevard du Temple 75011 Paris', 'postcode'=>'75011', 'city'=>'Paris', 'citycode'=>'75111', 'latitude'=>48.866, 'longitude'=>2.364, 'id'=>'BAN-46', 'type'=>'housenumber', 'score'=>0.92]]]; }
            public function reverse(float $latitude, float $longitude, int $limit = 3): array { return ['ok'=>true, 'query'=>'', 'cached'=>false, 'error'=>null, 'features'=>[['label'=>'48 Boulevard du Temple 75011 Paris']]]; }
        });
    }

    public function test_autocomplete_is_server_side_limited_and_maps_provider_fields(): void
    {
        $service = app(AddressSuggestionService::class);
        $this->assertSame([], $service->suggest('ab'));
        $suggestion = $service->suggest('46 boulevard du temple')[0];
        $this->assertStringContainsString('75011 Paris', $suggestion['label']);
        $this->assertSame('75111', $service->structured($service->resolve($suggestion['token']))['city_code']);
    }

    public function test_autocomplete_endpoint_is_admin_only_and_rate_limited_route_uses_safe_contract(): void
    {
        $this->getJson(route('admin.location.autocomplete', ['q' => 'Paris']))->assertUnauthorized();
        $admin = User::factory()->create(['role'=>'admin']);
        $this->actingAs($admin)->getJson(route('admin.location.autocomplete', ['q' => 'Paris']))->assertOk()->assertJsonPath('data.0.label', '46 Boulevard du Temple 75011 Paris');
        $this->actingAs($admin)->getJson(route('admin.location.autocomplete', ['q' => 'ab']))->assertUnprocessable();
    }

    public function test_selecting_an_address_keeps_osha_historical_address_until_explicit_selection_and_is_audited(): void
    {
        $admin = User::factory()->create(['role'=>'admin']); $this->actingAs($admin);
        $restaurant = $this->restaurant(['address'=>'46 Boulevard du Temple, Paris, France', 'address_line1'=>'46 Boulevard du Temple', 'postal_code'=>'75011', 'city_name'=>'Paris', 'city_code'=>'75111', 'country_code'=>'FR', 'latitude'=>48.866, 'longitude'=>2.364, 'geocoding_source_id'=>'BAN-46']);
        // A reverse result for 48 is never consumed by the editing service.
        app(RestaurantLocationService::class)->update($restaurant, ['name'=>$restaurant->name, 'location_update_source'=>'form']);
        $this->assertSame('46 Boulevard du Temple', $restaurant->fresh()->address_line1);

        $feature = app(AddressSuggestionService::class)->suggest('46 boulevard')[0]['feature'];
        app(RestaurantLocationService::class)->update($restaurant->fresh(), [...app(AddressSuggestionService::class)->structured($feature), 'location_update_source'=>'autocomplete']);
        $fresh = $restaurant->fresh();
        $this->assertSame('46 Boulevard du Temple', $fresh->address_line1); $this->assertSame('VERIFIED', $fresh->geocoding_status);
        $this->assertDatabaseHas('admin_audit_logs', ['action'=>'restaurant.location_updated', 'subject_id'=>$restaurant->id]);
    }

    public function test_manual_marker_move_requires_review_and_preserves_provider_provenance(): void
    {
        $admin = User::factory()->create(['role'=>'admin']); $this->actingAs($admin);
        $restaurant = $this->restaurant(['latitude'=>48.8, 'longitude'=>2.3, 'geocoding_provider'=>'geoplateforme', 'geocoding_source_id'=>'BAN-46', 'geocoding_status'=>'VERIFIED']);
        app(RestaurantLocationService::class)->update($restaurant, ['latitude'=>48.81, 'longitude'=>2.31, 'location_update_source'=>'map']);
        $fresh = $restaurant->fresh();
        $this->assertSame('MANUAL', $fresh->geocoding_status); $this->assertSame('MANUAL', $fresh->geocoding_precision); $this->assertNotNull($fresh->manually_verified_at);
        $this->assertSame('BAN-46', $fresh->geocoding_source_id); $this->assertSame('REVIEW_REQUIRED', $fresh->proximity_status);
    }

    public function test_duplicate_detector_is_informative_not_an_automatic_merge(): void
    {
        $first = $this->restaurant(['name'=>'O Sha', 'latitude'=>48.866, 'longitude'=>2.364, 'address_line1'=>'46 Boulevard du Temple']);
        $second = $this->restaurant(['name'=>'O Sha', 'latitude'=>48.8661, 'longitude'=>2.3641, 'address_line1'=>'46 Boulevard du Temple']);
        $this->assertCount(1, app(DuplicateRestaurantDetector::class)->candidates($first));
        $this->assertDatabaseHas('restaurants', ['id'=>$second->id]);
    }

    private function restaurant(array $attributes = []): Restaurant
    {
        return Restaurant::create($attributes + ['legacy_wp_id'=>random_int(1, 999999999), 'name'=>'Restaurant '.str()->random(8), 'slug'=>'restaurant-'.str()->random(8), 'status'=>'published']);
    }
}
