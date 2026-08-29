<?php

namespace Tests\Feature;

use App\Models\{Article, Category, Comment, Feature, Location, Page, Restaurant, RestaurantOutboundLink, RestaurantReview};
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
        $this->get('/resto/adams-burger')->assertOk()->assertSee('<title>Adam&#039;s Burger | Top Halal</title>', false)->assertDontSee('Adam&amp;#039;s Burger', false);
    }

    public function test_every_dynamic_public_page_title_is_escaped_once(): void
    {
        $restaurant = Restaurant::create(['legacy_wp_id' => 414, 'name' => "Adam's Burger", 'slug' => 'adams-burger', 'status' => 'published']);
        $category = Category::create(['legacy_term_id' => 15, 'name' => "Cuisine d'Orient", 'slug' => 'orient']);
        $feature = Feature::create(['legacy_term_id' => 16, 'name' => "Chef d'œuvre", 'slug' => 'chef-oeuvre']);
        $location = Location::create(['legacy_term_id' => 17, 'name' => "L'Haÿ-les-Roses", 'slug' => 'lhay']);
        $restaurant->categories()->attach($category); $restaurant->features()->attach($feature); $restaurant->locations()->attach($location);
        Article::create(['legacy_wp_id' => 18, 'original_title' => "L'article", 'title' => "L'article", 'slug' => 'article-test', 'legacy_url' => '/article-test', 'status' => 'published']);
        Page::create(['legacy_wp_id' => 19, 'original_title' => "La page d'accueil", 'title' => "La page d'accueil", 'slug' => 'page-test', 'legacy_url' => '/page-test', 'status' => 'published']);
        foreach (['/resto/adams-burger', '/article-test', '/page-test', '/restos/lhay', '/specialites/orient', '/service/chef-oeuvre'] as $url) {
            $this->get($url)->assertOk()->assertDontSee('&amp;#039;', false);
        }
    }

    public function test_public_comments_and_reviews_show_their_publication_date(): void
    {
        $restaurant = Restaurant::create(['legacy_wp_id' => 420, 'name' => 'Date test', 'slug' => 'date-test', 'status' => 'published']);
        RestaurantReview::create(['restaurant_id' => $restaurant->id, 'author_name' => 'Amina', 'rating' => 5, 'content' => 'Très bien', 'status' => 'approved', 'created_at' => '2020-02-03 12:00:00']);
        $article = Article::create(['legacy_wp_id' => 421, 'original_title' => 'Article', 'title' => 'Article', 'slug' => 'article-date', 'legacy_url' => '/article-date', 'status' => 'published']);
        Comment::create(['article_id' => $article->id, 'author_name' => 'Samir', 'content' => 'Merci', 'status' => 'approved', 'created_at' => '2020-02-03 12:00:00']);
        $this->get('/resto/date-test')->assertOk()->assertSee('Publié le 3 février 2020');
        $this->get('/article-date')->assertOk()->assertSee('Publié le 3 février 2020');
    }
}
