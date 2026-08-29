<?php

namespace Tests\Feature;

use App\Models\{Category, Feature, Location, Restaurant, RestaurantOutboundLink};
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class PublicFrontendTest extends TestCase
{
    use DatabaseMigrations;

    public function test_directory_filters_real_v2_relations_and_is_noindex_when_filtered(): void
    {
        $restaurant = Restaurant::create(['legacy_wp_id' => 411, 'name' => 'Le Safran', 'slug' => 'le-safran', 'status' => 'published', 'city_name' => 'Lyon']);
        $category = Category::create(['legacy_term_id' => 12, 'name' => 'Marocain', 'slug' => 'marocain']);
        $feature = Feature::create(['legacy_term_id' => 13, 'name' => 'À emporter', 'slug' => 'a-emporter']);
        $location = Location::create(['legacy_term_id' => 14, 'name' => 'Lyon', 'slug' => 'lyon']);
        $restaurant->categories()->attach($category); $restaurant->features()->attach($feature); $restaurant->locations()->attach($location);
        $this->get('/restaurants?q=safran&ville=lyon&categories[]=marocain&features[]=a-emporter')->assertOk()->assertSee('Le Safran')->assertSee('noindex,follow', false);
    }

    public function test_outbound_route_redirects_without_exposing_destination_in_restaurant_html(): void
    {
        $restaurant = Restaurant::create(['legacy_wp_id' => 412, 'name' => 'Le Cèdre', 'slug' => 'le-cedre', 'status' => 'published']);
        $link = RestaurantOutboundLink::create(['restaurant_id' => $restaurant->id, 'token' => 'aBcDeFgHiJkLmNoPqRsT1234', 'label' => 'Réserver', 'destination_url' => 'https://example.test/reservation']);
        $this->get('/resto/le-cedre')->assertOk()->assertDontSee('example.test');
        $this->get('/sortie/'.$link->token)->assertRedirect('https://example.test/reservation');
        $this->assertSame(1, $link->fresh()->click_count);
    }

    public function test_restaurant_title_is_not_double_encoded(): void
    {
        Restaurant::create(['legacy_wp_id' => 413, 'name' => "Adam's Burger", 'slug' => 'adams-burger', 'status' => 'published']);
        $this->get('/resto/adams-burger')->assertOk()->assertSee("<title>Adam's Burger | Top Halal</title>", false)->assertDontSee('Adam&amp;#039;s Burger', false);
    }
}
