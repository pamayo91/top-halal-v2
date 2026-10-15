<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class RestaurantBreadcrumbsTest extends TestCase
{
    use DatabaseMigrations;

    public function test_restaurant_breadcrumbs_use_the_existing_structured_geography_urls_without_duplicates(): void
    {
        $marseille = $this->published(1001, 'Bistrot Marseille', 'bistrot-marseille', 'Marseille', '13206');
        $paris = $this->published(1002, 'Bistrot Paris', 'bistrot-paris', 'Paris', '75111');
        $lyon = $this->published(1003, 'Bistrot Lyon', 'bistrot-lyon', 'Lyon', '69381');
        $risOrangis = $this->published(1004, 'Bistrot Ris', 'bistrot-ris', 'Ris-Orangis', '91521');
        $saintDenis = $this->published(1005, 'Bistrot Saint-Denis', 'bistrot-saint-denis', 'Saint-Denis', '93066');
        $this->published(1006, 'Bistrot Saint-Denis Réunion', 'bistrot-saint-denis-reunion', 'Saint-Denis', '97411');
        $pointeAPitre = $this->published(1007, 'Bistrot Pointe-à-Pitre', 'bistrot-pointe-a-pitre', 'Pointe-à-Pitre', '97120');

        // This unrelated commune reserves the short city URL, so the existing
        // geographic resolver must expose the department as /restos/indre-36.
        $this->published(1008, 'Bistrot Indre commune', 'bistrot-indre-commune', 'Indre', '44074');
        $chateauroux = $this->published(1009, 'Bistrot Châteauroux', 'bistrot-chateauroux', 'Châteauroux', '36044');

        $this->assertRestaurantBreadcrumb($marseille, [
            ['Accueil', route('home')], ['Restaurants', route('restaurants.index')], ["Provence-Alpes-Côte d'Azur", route('cities.show', 'provence-alpes-cote-d-azur')], ['Bouches-du-Rhône', route('cities.show', 'bouches-du-rhone')], ['Marseille', route('cities.show', 'marseille')], [$marseille->name, route('restaurants.show', $marseille->slug)],
        ]);
        $this->assertRestaurantBreadcrumb($paris, [
            ['Accueil', route('home')], ['Restaurants', route('restaurants.index')], ['Île-de-France', route('cities.show', 'ile-de-france')], ['Paris', route('cities.show', 'paris')], [$paris->name, route('restaurants.show', $paris->slug)],
        ]);
        $this->assertRestaurantBreadcrumb($lyon, [
            ['Accueil', route('home')], ['Restaurants', route('restaurants.index')], ['Auvergne-Rhône-Alpes', route('cities.show', 'auvergne-rhone-alpes')], ['Rhône', route('cities.show', 'rhone')], ['Lyon', route('cities.show', 'lyon')], [$lyon->name, route('restaurants.show', $lyon->slug)],
        ]);
        $this->assertRestaurantBreadcrumb($risOrangis, [
            ['Accueil', route('home')], ['Restaurants', route('restaurants.index')], ['Île-de-France', route('cities.show', 'ile-de-france')], ['Essonne', route('cities.show', 'essonne')], ['Ris-Orangis', route('cities.show', 'ris-orangis')], [$risOrangis->name, route('restaurants.show', $risOrangis->slug)],
        ]);
        $this->assertRestaurantBreadcrumb($saintDenis, [
            ['Accueil', route('home')], ['Restaurants', route('restaurants.index')], ['Île-de-France', route('cities.show', 'ile-de-france')], ['Seine-Saint-Denis', route('cities.show', 'seine-saint-denis')], ['Saint-Denis', route('cities.show', 'saint-denis-93')], [$saintDenis->name, route('restaurants.show', $saintDenis->slug)],
        ]);
        $this->assertRestaurantBreadcrumb($this->published(1010, 'Bistrot Saint-Denis Réunion bis', 'bistrot-saint-denis-reunion-bis', 'Saint-Denis', '97411'), [
            ['Accueil', route('home')], ['Restaurants', route('restaurants.index')], ['La Réunion', route('cities.show', 'la-reunion')], ['Saint-Denis', route('cities.show', 'saint-denis-974')], ['Bistrot Saint-Denis Réunion bis', route('restaurants.show', 'bistrot-saint-denis-reunion-bis')],
        ]);
        $this->assertRestaurantBreadcrumb($pointeAPitre, [
            ['Accueil', route('home')], ['Restaurants', route('restaurants.index')], ['Guadeloupe', route('cities.show', 'guadeloupe')], ['Pointe-à-Pitre', route('cities.show', 'pointe-a-pitre')], [$pointeAPitre->name, route('restaurants.show', $pointeAPitre->slug)],
        ]);
        $this->assertRestaurantBreadcrumb($chateauroux, [
            ['Accueil', route('home')], ['Restaurants', route('restaurants.index')], ['Centre-Val de Loire', route('cities.show', 'centre-val-de-loire')], ['Indre', route('cities.show', 'indre-36')], ['Châteauroux', route('cities.show', 'chateauroux')], [$chateauroux->name, route('restaurants.show', $chateauroux->slug)],
        ]);

        foreach ([
            ['Mayenne', '53147', 'Laval', '53130', 'mayenne-53'],
            ['Vienne', '38544', 'Poitiers', '86194', 'vienne-86'],
        ] as [$collidingCity, $collidingCityCode, $departmentCity, $departmentCityCode, $departmentSlug]) {
            $this->published(1100 + strlen($collidingCity), 'Commune '.$collidingCity, 'commune-'.(string) str($collidingCity)->slug(), $collidingCity, $collidingCityCode);
            $restaurant = $this->published(1200 + strlen($collidingCity), 'Restaurant '.$departmentCity, 'restaurant-'.(string) str($departmentCity)->slug(), $departmentCity, $departmentCityCode);
            $department = $departmentCityCode === '53130' ? 'Mayenne' : 'Vienne';
            $region = $departmentCityCode === '53130' ? 'Pays de la Loire' : 'Nouvelle-Aquitaine';
            $regionSlug = $departmentCityCode === '53130' ? 'pays-de-la-loire' : 'nouvelle-aquitaine';

            $this->assertRestaurantBreadcrumb($restaurant, [
                ['Accueil', route('home')], ['Restaurants', route('restaurants.index')], [$region, route('cities.show', $regionSlug)], [$department, route('cities.show', $departmentSlug)], [$departmentCity, route('cities.show', (string) str($departmentCity)->slug())], [$restaurant->name, route('restaurants.show', $restaurant->slug)],
            ]);
        }
    }

    /** @param list<array{string,string}> $expected */
    private function assertRestaurantBreadcrumb(Restaurant $restaurant, array $expected): void
    {
        $response = $this->get(route('restaurants.show', $restaurant->slug))->assertOk();
        $html = $response->getContent();
        $breadcrumb = $this->breadcrumbStructuredData($html);

        $this->assertSame(array_column($expected, 0), array_column($breadcrumb, 'name'));
        $this->assertSame(array_column($expected, 1), array_column($breadcrumb, 'item'));
        $this->assertSame(count($breadcrumb), count(array_unique(array_column($breadcrumb, 'name'))));
        $this->assertStringContainsString('<span aria-current="page">'.$restaurant->name.'</span>', $html);
        $this->assertStringNotContainsString('<a href="'.route('restaurants.show', $restaurant->slug).'">'.$restaurant->name.'</a>', $html);
        $this->assertMatchesRegularExpression('/<link rel="canonical" href="'.preg_quote(route('restaurants.show', $restaurant->slug), '/').'"/', $html);

        foreach (array_slice($expected, 0, -1) as [$label, $url]) {
            $this->assertStringContainsString('<a href="'.$url.'">'.e($label).'</a>', $html);
        }
    }

    /** @return list<array{position:int,name:string,item:string}> */
    private function breadcrumbStructuredData(string $html): array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);

        foreach ($matches[1] as $script) {
            $data = json_decode($script, true, 512, JSON_THROW_ON_ERROR);
            if (($data['@type'] ?? null) === 'BreadcrumbList') {
                return $data['itemListElement'];
            }
        }

        $this->fail('BreadcrumbList JSON-LD is missing.');
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
