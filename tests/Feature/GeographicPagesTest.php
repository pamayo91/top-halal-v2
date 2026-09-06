<?php

namespace Tests\Feature;

use App\Models\{CitySeoPage, Restaurant, Setting};
use App\Services\CitySeoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeographicPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_city_department_and_region_pages_use_structured_administrative_codes_and_breadcrumb_json_ld(): void
    {
        foreach (range(1, 13) as $number) {
            $this->published(100 + $number, "Marseille {$number}", 'marseille-'.$number, 'Marseille', '13206');
        }
        $this->published(201, 'Aix visible', 'aix-visible', 'Aix-en-Provence', '13001');
        $this->published(202, 'Avignon hors département', 'avignon-visible', 'Avignon', '84007');

        $this->get('/restos/marseille')
            ->assertOk()
            ->assertSee('Marseille 1')
            ->assertSee('Provence-Alpes-Côte d\'Azur')
            ->assertSee('Bouches-du-Rhône')
            ->assertSee('"@context":"https://schema.org"', false)
            ->assertSee('"@type":"BreadcrumbList"', false)
            ->assertSee('rel="canonical" href="'.route('cities.show', 'marseille').'"', false);

        $this->get('/restos/bouches-du-rhone')
            ->assertOk()
            ->assertSee('Aix visible')
            ->assertDontSee('Avignon hors département')
            ->assertSee('"@type":"BreadcrumbList"', false);
        $this->get('/restos/bouches-du-rhone?page=2')->assertOk()->assertSee('noindex,follow', false);

        $this->get('/restos/provence-alpes-cote-d-azur')
            ->assertOk()
            ->assertSee('Aix visible')
            ->assertSee('15 résultats')
            ->assertSee('"@type":"BreadcrumbList"', false);
    }

    public function test_homonymous_city_names_have_precise_pages_and_a_noindex_disambiguation_page(): void
    {
        $seineSaintDenis = $this->published(301, 'Saint-Denis 93', 'saint-denis-93-restaurant', 'Saint-Denis', '93066');
        $reunion = $this->published(302, 'Saint-Denis 974', 'saint-denis-974-restaurant', 'Saint-Denis', '97411');

        $this->get('/restos/saint-denis-93')
            ->assertOk()
            ->assertSee($seineSaintDenis->name)
            ->assertDontSee($reunion->name)
            ->assertSee('Île-de-France')
            ->assertSee('Seine-Saint-Denis');
        $this->get('/restos/saint-denis-974')
            ->assertOk()
            ->assertSee($reunion->name)
            ->assertDontSee($seineSaintDenis->name)
            ->assertSee('La Réunion');

        $this->get('/restos/saint-denis')
            ->assertOk()
            ->assertSee('noindex,follow', false)
            ->assertSee('/restos/saint-denis-93', false)
            ->assertSee('/restos/saint-denis-974', false)
            ->assertDontSee($seineSaintDenis->name)
            ->assertDontSee($reunion->name)
            ->assertSee('"@type":"BreadcrumbList"', false);
    }

    public function test_a_department_slug_is_automatically_suffixed_when_a_distinct_city_owns_the_short_url(): void
    {
        $cases = [
            ['Indre', '44074', 'Châteauroux', '36044', 'indre-36'],
            ['Mayenne', '53147', 'Laval', '53130', 'mayenne-53'],
            ['Vienne', '38544', 'Poitiers', '86194', 'vienne-86'],
        ];

        foreach ($cases as $index => [$cityName, $cityCode, $departmentCity, $departmentCityCode, $departmentSlug]) {
            $cityRestaurant = $this->published(350 + $index * 2, $cityName.' commune', 'city-'.$index, $cityName, $cityCode);
            $departmentRestaurant = $this->published(351 + $index * 2, $departmentCity.' département', 'department-'.$index, $departmentCity, $departmentCityCode);

            $this->get('/restos/'.str($cityName)->slug())
                ->assertOk()
                ->assertSee($cityRestaurant->name)
                ->assertDontSee($departmentRestaurant->name);
            $this->get('/restos/'.$departmentSlug)
                ->assertOk()
                ->assertSee($departmentRestaurant->name)
                ->assertDontSee($cityRestaurant->name);
        }
    }

    public function test_paris_and_municipal_arrondissements_resolve_to_a_single_page_without_a_duplicate_department_level(): void
    {
        $this->published(401, 'Paris commune', 'paris-commune', 'Paris', '75056');
        $this->published(402, 'Paris arrondissement', 'paris-arrondissement', 'Paris', '75111');
        foreach (range(1, 3) as $number) {
            $this->published(402 + $number, "Paris {$number}", "paris-{$number}", 'Paris', '75111');
        }

        $response = $this->get('/restos/paris');
        $response->assertOk()
            ->assertSee('Paris commune')
            ->assertSee('Paris arrondissement')
            ->assertSee('Île-de-France')
            ->assertSee('"@type":"BreadcrumbList"', false);
        $this->assertSame(1, substr_count($response->getContent(), 'aria-current="page">Paris</span>'));

        $sitemap = $this->get('/sitemap.xml')->assertOk();
        $this->assertSame(1, substr_count($sitemap->getContent(), route('cities.show', 'paris')));
    }

    public function test_city_overrides_are_independent_for_homonymous_communes_and_sitemap_excludes_closed_and_disambiguation_pages(): void
    {
        Setting::create(['key' => 'city_seo_minimum_restaurants', 'value' => ['value' => 5], 'group' => 'seo']);
        foreach (range(1, 5) as $number) {
            $this->published(500 + $number, "Marly nord {$number}", "marly-nord-{$number}", 'Marly', '59383');
        }
        $this->published(510, 'Marly moselle', 'marly-moselle', 'Marly', '57447');
        CitySeoPage::create(['city_name' => 'Marly', 'city_code' => '59383', 'state' => 'forced_closed']);
        CitySeoPage::create(['city_name' => 'Marly', 'city_code' => '57447', 'state' => 'forced_open']);
        app(CitySeoService::class)->forget();

        $this->get('/restos/marly-59')->assertOk()->assertSee('noindex,follow', false);
        $this->get('/restos/marly-57')->assertOk()->assertSee('index,follow', false);

        $sitemap = $this->get('/sitemap.xml')->assertOk();
        $sitemap->assertDontSee('/restos/marly-59', false)
            ->assertSee('/restos/marly-57', false)
            ->assertDontSee('/restos/marly</loc>', false);
    }

    public function test_corsica_and_overseas_codes_resolve_without_external_calls(): void
    {
        $this->published(601, 'Ajaccio', 'ajaccio-test', 'Ajaccio', '2A004');
        $this->published(602, 'Bastia', 'bastia-test', 'Bastia', '2B033');
        $this->published(603, 'Pointe-à-Pitre', 'pap-test', 'Pointe-à-Pitre', '97120');

        $this->get('/restos/ajaccio')->assertOk()->assertSee('Corse-du-Sud')->assertSee('Corse');
        $this->get('/restos/guadeloupe')->assertOk()->assertSee('Pointe-à-Pitre')->assertSee('Guadeloupe');
    }

    private function published(int $legacyId, string $name, string $slug, string $city, string $cityCode): Restaurant
    {
        return Restaurant::create([
            'legacy_wp_id' => $legacyId,
            'name' => $name,
            'slug' => $slug,
            'status' => 'published',
            'city_name' => $city,
            'city_code' => $cityCode,
            'country_code' => 'FR',
        ]);
    }
}
