<?php

namespace Tests\Feature;

use App\Models\{Category, CitySpecialtySeoPage, Restaurant, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CitySpecialtySeoPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_an_explicitly_open_city_specialty_is_a_public_seo_landing_page(): void
    {
        $burger = $this->specialty('Burger', 'burger');
        $indienne = $this->specialty('Indienne', 'indienne');
        $marseilleBurger = $this->published('Marseille Burger', 'marseille-burger', 'Marseille', '13206', $burger);
        $this->published('Marseille Indienne', 'marseille-indienne', 'Marseille', '13206', $indienne);

        $this->get('/restos/marseille/burger')->assertNotFound();
        $this->assertDatabaseMissing('city_specialty_seo_pages', ['city_code' => '13055', 'category_id' => $burger->id]);

        $facet = CitySpecialtySeoPage::create([
            'city_code' => '13055',
            'category_id' => $burger->id,
            'state' => 'open',
            'content_top' => '<p>Contenu haut contrôlé.</p>',
            'content_bottom' => '<p>Contenu bas contrôlé.</p>',
        ]);

        $this->get('/restos/marseille/burger')
            ->assertOk()
            ->assertSee($marseilleBurger->name)
            ->assertDontSee('Marseille Indienne')
            ->assertSee('Restaurants Burger halal à Marseille')
            ->assertSee('index,follow', false)
            ->assertSee('rel="canonical" href="'.route('city-specialties.show', ['city' => 'marseille', 'facet' => 'burger']).'"', false)
            ->assertSee('Contenu haut contrôlé.')
            ->assertSee('Contenu bas contrôlé.')
            ->assertSee('Provence-Alpes-Côte d\'Azur')
            ->assertSee('Bouches-du-Rhône')
            ->assertSee('"@type":"BreadcrumbList"', false);

        $this->get('/restos/marseille')->assertOk()
            ->assertSee('Restaurants halal par spécialité à Marseille')
            ->assertSee(route('city-specialties.show', ['city' => 'marseille', 'facet' => 'burger']), false)
            ->assertDontSee('/restos/marseille/indienne', false);
        $this->get('/sitemap.xml')->assertSee('/restos/marseille/burger', false)->assertDontSee('/restos/marseille/indienne', false);

        $facet->update(['state' => 'closed']);
        $this->get('/restos/marseille/burger')->assertNotFound();
        $this->get('/restos/marseille')->assertDontSee('/restos/marseille/burger', false);
        $this->get('/sitemap.xml')->assertDontSee('/restos/marseille/burger', false);
    }

    public function test_filters_remain_usable_when_a_matching_facet_is_closed_and_an_open_facet_can_use_its_canonical_url(): void
    {
        $burger = $this->specialty('Burger', 'burger');
        $restaurant = $this->published('Burger Marseille', 'burger-marseille', 'Marseille', '13206', $burger);

        $this->get('/restaurants?ville=marseille&categories[]=burger')->assertOk()->assertSee($restaurant->name);
        $this->get('/restaurants/recherche?ville=marseille&categories[]=burger')->assertRedirect('/restaurants?ville=marseille&categories%5B0%5D=burger');

        CitySpecialtySeoPage::create(['city_code' => '13055', 'category_id' => $burger->id, 'state' => 'open']);
        $this->get('/restaurants/recherche?ville=marseille&categories[]=burger')->assertRedirect('/restos/marseille/burger');
    }

    public function test_precise_city_resolver_is_reused_for_paris_lyon_and_homonymous_city_facets(): void
    {
        $burger = $this->specialty('Burger', 'burger');
        $this->published('Paris Burger', 'paris-burger', 'Paris', '75111', $burger);
        $this->published('Lyon Burger', 'lyon-burger', 'Lyon', '69381', $burger);
        $north = $this->published('Saint-Denis 93 Burger', 'saint-denis-93-burger', 'Saint-Denis', '93066', $burger);
        $reunion = $this->published('Saint-Denis 974 Burger', 'saint-denis-974-burger', 'Saint-Denis', '97411', $burger);
        foreach (['75056', '69123', '93066'] as $cityCode) {
            CitySpecialtySeoPage::create(['city_code' => $cityCode, 'category_id' => $burger->id, 'state' => 'open']);
        }

        $this->get('/restos/paris/burger')->assertOk()->assertSee('Paris Burger');
        $this->get('/restos/lyon/burger')->assertOk()->assertSee('Lyon Burger');
        $this->get('/restos/saint-denis-93/burger')->assertOk()->assertSee($north->name)->assertDontSee($reunion->name);
        $this->get('/restos/saint-denis/burger')->assertNotFound();
        $this->get('/sitemap.xml')->assertSee('/restos/paris/burger', false)->assertSee('/restos/lyon/burger', false)->assertSee('/restos/saint-denis-93/burger', false)->assertDontSee('/restos/saint-denis-974/burger', false);
    }

    public function test_admin_explores_sparse_city_specialty_opportunities_and_only_persists_configuration(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $burger = $this->specialty('Burger', 'burger');
        $indienne = $this->specialty('Indienne', 'indienne');
        $this->published('Marseille Burger', 'admin-marseille-burger', 'Marseille', '13206', $burger);
        $this->published('Paris Indienne', 'admin-paris-indienne', 'Paris', '75056', $indienne);

        $this->actingAs($admin)->get('/admin/facettes-seo')->assertOk()->assertSee('Ville')->assertSee('Spécialité')->assertSee('Nombre de restaurants')->assertSee('Fermée');
        $this->assertDatabaseCount('city_specialty_seo_pages', 0);
        $this->actingAs($admin)->get('/admin/facettes-seo?city=13055&specialty='.$burger->id)
            ->assertOk()
            ->assertSee('Modification :')
            ->assertSee('Marseille + Burger');
        $this->assertDatabaseCount('city_specialty_seo_pages', 0);

        CitySpecialtySeoPage::create(['city_code' => '13055', 'category_id' => $burger->id, 'state' => 'open']);
        $this->actingAs($admin)->get('/admin/facettes-seo')
            ->assertOk()
            ->assertSee('/restos/marseille/burger', false);
    }

    private function published(string $name, string $slug, string $city, string $cityCode, Category $category): Restaurant
    {
        $restaurant = Restaurant::create(['legacy_wp_id' => 99000 + Restaurant::count(), 'name' => $name, 'slug' => $slug, 'status' => 'published', 'city_name' => $city, 'city_code' => $cityCode, 'country_code' => 'FR']);
        $restaurant->categories()->attach($category);

        return $restaurant;
    }

    private function specialty(string $name, string $slug): Category
    {
        return Category::firstOrCreate(['slug' => $slug], ['legacy_term_id' => 20000 + Category::count(), 'name' => $name]);
    }
}
