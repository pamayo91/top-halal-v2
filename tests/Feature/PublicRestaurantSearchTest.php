<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\{RedirectRule, Restaurant};
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class PublicRestaurantSearchTest extends TestCase
{
    use DatabaseMigrations;

    public function test_home_search_defaults_to_paris_and_only_exposes_published_city_data(): void
    {
        Restaurant::create(['legacy_wp_id' => 1, 'name' => 'Paris publié', 'slug' => 'paris-publie', 'status' => 'published', 'city_name' => 'Paris']);
        Restaurant::create(['legacy_wp_id' => 2, 'name' => 'Caché', 'slug' => 'cache', 'status' => 'pending', 'city_name' => 'Ville cachée']);

        $this->get('/')->assertOk()->assertSee('Localisation')->assertSee('value="Paris"', false)->assertSee('Paris publié')->assertDontSee('Ville cachée');
        $this->getJson('/restaurants/recherche/villes?q=par')->assertOk()->assertJsonPath('cities.0.slug', 'paris');
    }

    public function test_suggestions_return_real_specialties_and_prioritize_selected_city_restaurants(): void
    {
        $burger = Category::create(['legacy_term_id' => 1, 'name' => 'Burger', 'slug' => 'burger']);
        $paris = Restaurant::create(['legacy_wp_id' => 3, 'name' => 'Black Paris', 'slug' => 'black-paris', 'status' => 'published', 'city_name' => 'Paris']);
        $lyon = Restaurant::create(['legacy_wp_id' => 4, 'name' => 'Black Lyon', 'slug' => 'black-lyon', 'status' => 'published', 'city_name' => 'Lyon']);
        $paris->categories()->attach($burger);
        $lyon->categories()->attach($burger);

        $this->getJson('/restaurants/recherche/suggestions?q=black&ville=paris')->assertOk()->assertJsonPath('restaurants.0.slug', 'black-paris');
        $this->getJson('/restaurants/recherche/suggestions?q=burg')->assertOk()->assertJsonPath('specialties.0.slug', 'burger');
    }

    public function test_city_only_search_uses_city_name_slug_and_other_combinations_stay_noindex_results(): void
    {
        $burger = Category::create(['legacy_term_id' => 2, 'name' => 'Burger', 'slug' => 'burger']);
        $restaurant = Restaurant::create(['legacy_wp_id' => 5, 'name' => 'Burger Paris', 'slug' => 'burger-paris', 'status' => 'published', 'city_name' => 'Paris']);
        $restaurant->categories()->attach($burger);

        $this->get('/restaurants/recherche?ville=paris')->assertRedirect('/restos/paris');
        $this->get('/restos/paris')->assertOk()->assertSee('Burger Paris');
        $this->get('/restaurants/recherche?ville=paris&categories[]=burger')->assertRedirect('/restaurants?ville=paris&categories%5B0%5D=burger');
        $this->get('/restaurants?ville=paris&categories[]=burger')->assertOk()->assertSee('Burger Paris')->assertSee('noindex,follow', false);
    }

    public function test_search_routes_are_not_intercepted_by_a_legacy_redirect_rule(): void
    {
        Restaurant::create(['legacy_wp_id' => 6, 'name' => 'Paris publié', 'slug' => 'paris-publie', 'status' => 'published', 'city_name' => 'Paris']);
        RedirectRule::create(['source_path' => '/restaurants/recherche', 'match_type' => 'exact', 'destination' => '/resto/recherche', 'status_code' => 301, 'priority' => 1, 'is_active' => true]);

        $this->get('/restaurants/recherche?ville=paris')->assertRedirect('/restos/paris');
    }
}
