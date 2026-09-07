<?php

namespace Tests\Feature;

use App\Models\{CityServiceSeoPage, Feature, Restaurant, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CityServiceSeoPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_facets_are_sparse_closed_by_default_and_publish_only_when_open(): void
    {
        $terrace = $this->service('Terrasse', 'terrasse');
        $delivery = $this->service('Livraison', 'livraison');
        $terraceRestaurant = $this->published('Terrasse Marseille', 'terrasse-marseille', 'Marseille', '13206', $terrace);
        $this->published('Livraison Marseille', 'livraison-marseille', 'Marseille', '13206', $delivery);

        $this->get('/restos/marseille/terrasse')->assertNotFound();
        $this->assertDatabaseCount('city_service_seo_pages', 0);
        CityServiceSeoPage::create(['city_code' => '13055', 'feature_id' => $terrace->id, 'state' => 'open', 'content_top' => '<p>Terrasse haut.</p>', 'content_bottom' => '<p>Terrasse bas.</p>']);

        $this->get('/restos/marseille/terrasse')->assertOk()->assertSee($terraceRestaurant->name)->assertDontSee('Livraison Marseille')->assertSee('Restaurants halal avec terrasse à Marseille')->assertSee('<title>Restaurants halal avec terrasse à Marseille | Top Halal</title>', false)->assertSee('Découvrez 1 restaurant halal avec terrasse à Marseille.', false)->assertSee('rel="canonical" href="'.route('city-specialties.show', ['city' => 'marseille', 'facet' => 'terrasse']).'"', false)->assertSee('index,follow', false)->assertSee('Terrasse haut.')->assertSee('Terrasse bas.')->assertSee('Provence-Alpes-Côte d\'Azur')->assertSee('Bouches-du-Rhône')->assertSee('"@type":"BreadcrumbList"', false);
        $this->get('/restos/marseille')->assertSee('Restaurants halal par service à Marseille')->assertSee('/restos/marseille/terrasse', false)->assertDontSee('/restos/marseille/livraison', false);
        $this->get('/sitemap.xml')->assertSee('/restos/marseille/terrasse', false)->assertDontSee('/restos/marseille/livraison', false);
        $this->get('/restaurants?ville=marseille&features[]=livraison')->assertOk()->assertSee('Livraison Marseille');
        $this->get('/restaurants/recherche?ville=marseille&features[]=terrasse')->assertRedirect('/restos/marseille/terrasse');

        CityServiceSeoPage::where('city_code', '13055')->where('feature_id', $terrace->id)->update(['state' => 'closed']);
        $this->get('/restos/marseille/terrasse')->assertNotFound();
        $this->get('/sitemap.xml')->assertDontSee('/restos/marseille/terrasse', false);
    }

    public function test_services_use_the_single_admin_facets_screen(): void
    {
        $terrace = $this->service('Terrasse', 'terrasse');
        $admin = User::factory()->create(['role' => 'admin']);
        $this->published('Marseille Terrasse', 'marseille-terrasse', 'Marseille', '13206', $terrace);
        $this->actingAs($admin)->get('/admin/facettes-seo?type=service&city=13055&service='.$terrace->id)->assertOk()->assertSee('Type de facette')->assertSee('Service')->assertSee('Marseille + Terrasse');
        $this->assertDatabaseCount('city_service_seo_pages', 0);
    }

    public function test_service_facets_reuse_the_precise_resolver_for_paris_lyon_and_homonymous_cities(): void
    {
        $terrace = $this->service('Terrasse', 'terrasse');
        $this->published('Paris Terrasse', 'paris-terrasse', 'Paris', '75111', $terrace);
        $this->published('Lyon Terrasse', 'lyon-terrasse', 'Lyon', '69381', $terrace);
        $north = $this->published('Saint-Denis 93 Terrasse', 'saint-denis-93-terrasse', 'Saint-Denis', '93066', $terrace);
        $reunion = $this->published('Saint-Denis 974 Terrasse', 'saint-denis-974-terrasse', 'Saint-Denis', '97411', $terrace);

        foreach (['75056', '69123', '93066'] as $cityCode) {
            CityServiceSeoPage::create(['city_code' => $cityCode, 'feature_id' => $terrace->id, 'state' => 'open']);
        }

        $this->get('/restos/paris/terrasse')->assertOk()->assertSee('Paris Terrasse');
        $this->get('/restos/lyon/terrasse')->assertOk()->assertSee('Lyon Terrasse');
        $this->get('/restos/saint-denis-93/terrasse')->assertOk()->assertSee($north->name)->assertDontSee($reunion->name);
        $this->get('/restos/saint-denis/terrasse')->assertNotFound();
        $this->get('/sitemap.xml')->assertSee('/restos/paris/terrasse', false)->assertSee('/restos/lyon/terrasse', false)->assertSee('/restos/saint-denis-93/terrasse', false)->assertDontSee('/restos/saint-denis-974/terrasse', false);
    }

    private function published(string $name, string $slug, string $city, string $cityCode, Feature $feature): Restaurant
    {
        $restaurant = Restaurant::create(['legacy_wp_id' => 88000 + Restaurant::count(), 'name' => $name, 'slug' => $slug, 'status' => 'published', 'city_name' => $city, 'city_code' => $cityCode, 'country_code' => 'FR']);
        $restaurant->features()->attach($feature);
        return $restaurant;
    }

    private function service(string $name, string $slug): Feature
    {
        return Feature::firstOrCreate(['slug' => $slug], ['legacy_term_id' => 30000 + Feature::count(), 'name' => $name]);
    }
}
