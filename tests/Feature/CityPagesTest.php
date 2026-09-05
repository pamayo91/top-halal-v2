<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class CityPagesTest extends TestCase
{
    use DatabaseMigrations;

    public function test_city_pages_use_only_the_structured_city_name_and_keep_current_seo_markup(): void
    {
        $marseille = $this->published(1, 'Marseille visible', 'Marseille');
        $legacyOnly = $this->published(2, 'Legacy association only', 'Aubagne');
        $location = Location::create(['legacy_term_id' => 1, 'name' => 'Marseille', 'slug' => 'marseille']);
        $legacyOnly->locations()->attach($location);

        $this->get('/restos/marseille')
            ->assertOk()
            ->assertSee($marseille->name)
            ->assertDontSee($legacyOnly->name)
            ->assertSee('rel="canonical" href="'.route('cities.show', 'marseille').'"', false)
            ->assertSee('aria-label="Fil d’Ariane"', false)
            ->assertSee('Restaurants');
    }

    public function test_major_small_accented_and_apostrophe_city_slugs_resolve_from_city_name(): void
    {
        foreach ([
            'Paris' => 'paris',
            'Lyon' => 'lyon',
            'Ris-Orangis' => 'ris-orangis',
            'Échirolles' => 'echirolles',
            "L'Haÿ-les-Roses" => 'lhay-les-roses',
        ] as $city => $slug) {
            $restaurant = $this->published(10 + strlen($slug), 'Restaurant '.$city, $city);
            $this->get('/restos/'.$slug)->assertOk()->assertSee($restaurant->name);
        }
    }

    public function test_city_pagination_and_unknown_city_are_handled_without_legacy_location_fallback(): void
    {
        foreach (range(1, 13) as $number) {
            $this->published(100 + $number, 'Paris '.$number, 'Paris');
        }
        $legacy = Location::create(['legacy_term_id' => 99, 'name' => 'Legacy only', 'slug' => 'legacy-only']);
        $this->published(200, 'Restaurant legacy only', 'Aubagne')->locations()->attach($legacy);

        $this->get('/restos/paris?page=2')->assertOk()->assertSee('Paris 13')->assertSee('noindex,follow', false);
        $this->get('/restos/inexistante')->assertNotFound();
        $this->get('/restos/legacy-only')->assertNotFound();
    }

    private function published(int $legacyId, string $name, string $city): Restaurant
    {
        return Restaurant::create(['legacy_wp_id' => $legacyId, 'name' => $name, 'slug' => 'restaurant-'.$legacyId, 'status' => 'published', 'city_name' => $city]);
    }
}
