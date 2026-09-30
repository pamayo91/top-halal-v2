<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Restaurant;
use App\Services\CityPageResolver;
use App\Services\Quick\QuickRestaurantDirectory;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Tests\TestCase;

class EditorialQuickRestaurantsMapTest extends TestCase
{
    use DatabaseMigrations;

    public function test_it_uses_only_published_quick_namespace_records_and_groups_canonical_cities(): void
    {
        $this->quick('Paris 1', '75056');
        $this->quick('Paris 11e', '75111');
        $this->quick('Lyon 1', '69123');
        $this->quick('Lyon 8e', '69388');
        $this->quick('Marseille 1er', '13201');
        $this->quick('Marseille 2e', '13202');
        $this->quick('Lille', '59350', ['latitude' => null, 'longitude' => null]);
        $this->quick('En attente', '31555', ['status' => 'pending']);
        $deleted = $this->quick('Supprimé', '67482');
        $deleted->delete();
        $this->restaurant(['name' => 'Quick dans le nom seulement', 'slug' => 'restaurant-quick-ordinaire', 'city_code' => '44109']);
        app(CityPageResolver::class)->forget();

        $data = app(QuickRestaurantDirectory::class)->mapData();

        $this->assertNotNull($data);
        $this->assertSame([
            ['name' => 'Lyon', 'count' => 2],
            ['name' => 'Marseille', 'count' => 2],
            ['name' => 'Paris', 'count' => 2],
            ['name' => 'Lille', 'count' => 1],
        ], collect($data['cities'])->map(fn (array $city) => ['name' => $city['name'], 'count' => $city['count']])->all());
        $this->assertCount(6, $data['points']);
        $this->assertSame(route('restaurants.index', ['q' => 'Quick', 'city_code' => '69123']), $data['cities'][0]['url']);
        $this->assertSame(route('restaurants.index', ['q' => 'Quick', 'city_code' => '13055']), $data['cities'][1]['url']);
        $this->assertSame(route('restaurants.index', ['q' => 'Quick', 'city_code' => '75056']), $data['cities'][2]['url']);
        $this->assertNotContains('Quick dans le nom seulement', collect($data['points'])->pluck('name')->all());
        $this->assertNotContains('En attente', collect($data['points'])->pluck('name')->all());
        $this->assertNotContains('Supprimé', collect($data['points'])->pluck('name')->all());
    }

    public function test_the_editorial_token_renders_an_accessible_server_rendered_map_and_city_panel(): void
    {
        $this->quick('Quick Lyon', '69123');
        $this->quick('Quick Lille', '59350', ['latitude' => null, 'longitude' => null]);
        app(CityPageResolver::class)->forget();
        $page = Page::create([
            'legacy_wp_id' => 900001,
            'original_title' => 'Quick halal',
            'title' => 'Quick halal',
            'slug' => 'quick-halal-carte-'.Str::lower(Str::random(8)),
            'legacy_url' => '/quick-halal-carte',
            'content_html' => '<p>[quick_restaurants_map]</p>',
            'status' => 'published',
        ]);

        $this->get('/'.$page->slug)
            ->assertOk()
            ->assertSee('editorial-quick-map', false)
            ->assertSee('Carte des restaurants Quick halal en France')
            ->assertSee('Principales villes')
            ->assertSee('Lyon')
            ->assertSee('1 restaurant')
            ->assertSee('Lille')
            ->assertSee('Voir tous les Quick halal')
            ->assertSee('q=Quick&amp;city_code=69123', false)
            ->assertSee(route('restaurants.index', ['q' => 'Quick']), false)
            ->assertDontSee(route('cities.show', 'lyon'), false)
            ->assertDontSee('[quick_restaurants_map]', false);
    }

    public function test_the_token_is_hidden_when_no_published_quick_exists(): void
    {
        $page = Page::create([
            'legacy_wp_id' => 900002,
            'original_title' => 'Quick vide',
            'title' => 'Quick vide',
            'slug' => 'quick-vide-'.Str::lower(Str::random(8)),
            'legacy_url' => '/quick-vide',
            'content_html' => '<p>[quick_restaurants_map]</p>',
            'status' => 'published',
        ]);

        $this->get('/'.$page->slug)
            ->assertOk()
            ->assertDontSee('editorial-quick-map', false)
            ->assertDontSee('[quick_restaurants_map]', false);
    }

    private function quick(string $name, string $cityCode, array $attributes = []): Restaurant
    {
        return $this->restaurant($attributes + [
            'name' => 'Quick '.$name,
            'slug' => 'quick-'.Str::slug($name).'-'.Str::lower(Str::random(7)),
            'city_name' => str_starts_with($cityCode, '69') ? 'Lyon' : (str_starts_with($cityCode, '13') ? 'Marseille' : (str_starts_with($cityCode, '75') ? 'Paris' : 'Lille')),
            'city_code' => $cityCode,
            'latitude' => 48.8566,
            'longitude' => 2.3522,
        ]);
    }

    private function restaurant(array $attributes): Restaurant
    {
        return Restaurant::create($attributes + [
            'legacy_wp_id' => random_int(1, 800000),
            'name' => 'Restaurant '.Str::random(8),
            'slug' => 'restaurant-'.Str::lower(Str::random(10)),
            'status' => 'published',
        ]);
    }
}
