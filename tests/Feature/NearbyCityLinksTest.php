<?php

namespace Tests\Feature;

use App\Filament\Pages\SettingsPage;
use App\Models\{CityReferencePoint, CitySeoPage, Restaurant, Setting, User};
use App\Services\{CityPageResolver, CitySeoService, NearbyCityService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class NearbyCityLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_links_only_open_nearby_city_pages_in_distance_order_with_a_cross_department_candidate(): void
    {
        $this->city('Marseille', '13055', 43.2803, 5.3806);
        $this->city('Allauch', '13002', 43.3350, 5.4820, 'forced_open');
        $this->city('Saint-Zacharie', '83120', 43.3600, 5.6000, 'forced_open');
        $this->city('Aix-en-Provence', '13001', 43.5297, 5.4474, 'forced_open');
        $this->city('Ville fermée', '13003', 43.2900, 5.3900, 'forced_closed');
        $this->city('Ville lointaine', '84007', 44.0000, 5.0000, 'forced_open');
        $this->forgetCityCaches();

        $marseille = app(CityPageResolver::class)->cityForCode('13055');
        $nearby = app(NearbyCityService::class)->nearbyFor($marseille);

        $this->assertSame(['Allauch', 'Saint-Zacharie', 'Aix-en-Provence'], $nearby->pluck('city_name')->all());
        $this->assertSame('83120', $nearby->get(1)->city_code);
        $this->assertNotContains('Marseille', $nearby->pluck('city_name')->all());
        $this->assertNotContains('Ville fermée', $nearby->pluck('city_name')->all());
        $this->assertNotContains('Ville lointaine', $nearby->pluck('city_name')->all());

        Http::preventStrayRequests();
        $this->get('/restos/marseille')
            ->assertOk()
            ->assertSee('Villes aux alentours')
            ->assertSee('href="'.route('cities.show', 'saint-zacharie').'"', false)
            ->assertDontSee('Ville fermée');
    }

    public function test_radius_limit_and_seo_state_changes_invalidate_cached_candidates(): void
    {
        $this->city('Marseille', '13055', 43.2803, 5.3806);
        $this->city('Proche', '13001', 43.3500, 5.3806, 'forced_open');
        $far = $this->city('Lointaine', '13002', 43.6400, 5.3806, 'forced_open');
        $this->forgetCityCaches();

        $marseille = app(CityPageResolver::class)->cityForCode('13055');
        $service = app(NearbyCityService::class);
        $this->assertSame(['Proche'], $service->nearbyFor($marseille)->pluck('city_name')->all());

        Setting::updateOrCreate(['key' => 'city_nearby_radius_km'], ['value' => ['value' => 50], 'group' => 'seo']);
        $this->assertSame(['Proche', 'Lointaine'], $service->nearbyFor($marseille)->pluck('city_name')->all());

        CitySeoPage::where('city_code', $far->city_code)->firstOrFail()->update(['state' => 'forced_closed']);
        $this->assertSame(['Proche'], $service->nearbyFor($marseille)->pluck('city_name')->all());
    }

    public function test_it_applies_the_fifteen_city_default_and_uses_precise_homonym_urls(): void
    {
        $this->city('Marseille', '13055', 43.2803, 5.3806);
        foreach (range(1, 16) as $number) {
            $this->city('Proche '.$number, '13'.str_pad((string) $number, 3, '0', STR_PAD_LEFT), 43.2803 + ($number / 1000), 5.3806, 'forced_open');
        }
        $this->city('Saint-Denis', '93066', 43.28035, 5.3806, 'forced_open');
        $this->city('Saint-Denis', '97411', -20.8789, 55.4481, 'forced_open');
        $this->forgetCityCaches();

        $nearby = app(NearbyCityService::class)->nearbyFor(app(CityPageResolver::class)->cityForCode('13055'));
        $this->assertCount(NearbyCityService::DEFAULT_LIMIT, $nearby);
        $this->assertContains('saint-denis-93', $nearby->pluck('slug')->all());
        $this->assertNotContains('saint-denis-974', $nearby->pluck('slug')->all());

        $response = $this->get('/restos/marseille')->assertOk();
        $response->assertSee('nearby-cities-grid', false)
            ->assertSee('href="'.route('cities.show', 'saint-denis-93').'"', false)
            ->assertSeeInOrder(['Proche 1', 'Proche 2', 'Proche 3']);
    }

    public function test_it_hides_the_section_when_no_eligible_city_has_a_reference_point_in_range(): void
    {
        $this->city('Marseille', '13055', 43.2803, 5.3806);
        $this->city('Ville fermée', '13001', 43.2900, 5.3806, 'forced_closed');
        $this->forgetCityCaches();

        $this->get('/restos/marseille')->assertOk()->assertDontSee('Villes aux alentours');
    }

    public function test_the_admin_settings_store_the_global_radius_and_limit(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($admin)->test(SettingsPage::class)
            ->assertSet('data.city_nearby_radius_km', NearbyCityService::DEFAULT_RADIUS_KM)
            ->assertSet('data.city_nearby_maximum', NearbyCityService::DEFAULT_LIMIT)
            ->set('data.city_nearby_radius_km', 45)
            ->set('data.city_nearby_maximum', 20)
            ->call('save');

        $this->assertDatabaseHas('settings', ['key' => 'city_nearby_radius_km', 'value' => json_encode(['value' => 45]), 'group' => 'seo']);
        $this->assertDatabaseHas('settings', ['key' => 'city_nearby_maximum', 'value' => json_encode(['value' => 20]), 'group' => 'seo']);
    }

    public function test_the_reference_sync_uses_one_official_dataset_request_and_keeps_only_published_city_codes(): void
    {
        $this->city('Marseille', '13055', 43.2803, 5.3806);
        $this->city('Aix-en-Provence', '13001', 43.5297, 5.4474);
        $this->forgetCityCaches();
        CityReferencePoint::query()->delete();

        Http::fake([
            config('city-nearby.reference_source_url').'*' => Http::response([
                ['code' => '13055', 'centre' => ['coordinates' => [5.3806, 43.2803]]],
                ['code' => '13001', 'centre' => ['coordinates' => [5.4474, 43.5297]]],
                ['code' => '99999', 'centre' => ['coordinates' => [1, 1]]],
            ]),
        ]);

        $this->artisan('city-reference-points:sync')->assertSuccessful();

        $this->assertDatabaseHas('city_reference_points', ['city_code' => '13055', 'source' => 'geo.api.gouv.fr/communes']);
        $this->assertDatabaseHas('city_reference_points', ['city_code' => '13001', 'source' => 'geo.api.gouv.fr/communes']);
        $this->assertDatabaseMissing('city_reference_points', ['city_code' => '99999']);
        Http::assertSentCount(1);
    }

    private function city(string $name, string $code, float $latitude, float $longitude, string $state = 'auto'): Restaurant
    {
        $restaurant = Restaurant::create([
            'legacy_wp_id' => 900000 + Restaurant::withTrashed()->count(),
            'name' => $name.' restaurant',
            'slug' => str($name)->slug().'-'.strtolower($code),
            'status' => 'published',
            'city_name' => $name,
            'city_code' => $code,
            'country_code' => 'FR',
        ]);

        if ($state !== 'auto') {
            CitySeoPage::updateOrCreate(['city_code' => $code], ['city_name' => $name, 'state' => $state]);
        }

        CityReferencePoint::updateOrCreate(['city_code' => $code], [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'source' => 'test',
            'synced_at' => now(),
        ]);

        return $restaurant;
    }

    private function forgetCityCaches(): void
    {
        app(CitySeoService::class)->forget();
    }
}
