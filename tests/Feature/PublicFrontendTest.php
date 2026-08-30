<?php

namespace Tests\Feature;

use App\Models\{Article, Category, Comment, ContentMedia, Feature, Location, MediaAsset, Page, Restaurant, RestaurantMedia, RestaurantOutboundLink, RestaurantReview};
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

    public function test_article_detail_renders_its_featured_media(): void
    {
        $article = Article::create(['legacy_wp_id' => 422, 'original_title' => 'Article illustré', 'title' => 'Article illustré', 'slug' => 'article-illustre', 'legacy_url' => '/article-illustre', 'status' => 'published']);
        $asset = MediaAsset::create(['legacy_attachment_id' => 422, 'original_path' => 'media/originals/article.jpg', 'mime' => 'image/jpeg', 'width' => 1200, 'height' => 800, 'bytes' => 10, 'checksum' => str_repeat('c', 64), 'status' => 'ready']);
        ContentMedia::create(['content_type' => 'post', 'content_id' => $article->id, 'legacy_attachment_id' => 422, 'media_asset_id' => $asset->id, 'role' => 'featured']);

        $this->get('/article-illustre')->assertOk()->assertSee('article-featured-media', false)->assertSee(route('media.show', $asset), false);
    }

    public function test_restaurant_detail_falls_back_to_its_available_hero_variant(): void
    {
        $restaurant = Restaurant::create(['legacy_wp_id' => 423, 'name' => 'Restaurant illustré', 'slug' => 'restaurant-illustre', 'status' => 'published']);
        $asset = MediaAsset::create(['legacy_attachment_id' => 423, 'original_path' => 'media/originals/restaurant.jpg', 'mime' => 'image/jpeg', 'width' => 800, 'height' => 1200, 'bytes' => 10, 'checksum' => str_repeat('d', 64), 'status' => 'ready']);
        $asset->variants()->create(['format' => 'webp', 'width' => 480, 'height' => 720, 'path' => 'media/variants/restaurant-480.webp']);
        RestaurantMedia::create(['restaurant_id' => $restaurant->id, 'legacy_attachment_id' => 423, 'sort_order' => 0, 'status' => 'ready']);

        $this->get('/resto/restaurant-illustre')->assertOk()->assertSee(route('media.show', [$asset, 480]), false)->assertDontSee(route('media.show', [$asset, 960]), false);
    }

    public function test_restaurant_services_render_local_svg_icons_with_their_text_labels(): void
    {
        $restaurant = Restaurant::create(['legacy_wp_id' => 424, 'name' => 'Services test', 'slug' => 'services-test', 'status' => 'published']);
        $services = collect(['Accès handicapé', 'Ambiance musicale', 'Beau décor', 'Branché', 'Certifié halal', 'Original', 'Romantique', 'Salle de prière', 'Sans alcool', 'Terrasse', 'Traiteur', 'Vente à emporter', 'Wi-Fi'])
            ->map(fn (string $name, int $index) => Feature::create(['legacy_term_id' => 500 + $index, 'name' => $name, 'slug' => \Illuminate\Support\Str::slug($name)]));
        $restaurant->features()->attach($services->pluck('id'));

        $response = $this->get('/resto/services-test');

        $response->assertOk()->assertSee('Services')->assertSee('service-list', false)->assertSee('viewBox="0 0 24 24"', false);
        foreach ($services as $service) $response->assertSee($service->name);
    }
}
