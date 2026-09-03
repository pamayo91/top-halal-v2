<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\Comment;
use App\Models\ContentMedia;
use App\Models\Feature;
use App\Models\Location;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\Restaurant;
use App\Models\RestaurantMedia;
use App\Models\RestaurantOpeningHour;
use App\Models\RestaurantOutboundLink;
use App\Models\RestaurantReview;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicFrontendTest extends TestCase
{
    use DatabaseMigrations;

    public function test_active_admin_gets_a_direct_edit_shortcut_only_on_the_public_record_being_viewed(): void
    {
        $restaurant = Restaurant::create(['legacy_wp_id' => 430, 'name' => 'Raccourci restaurant', 'slug' => 'raccourci-restaurant', 'status' => 'published']);
        $article = Article::create(['legacy_wp_id' => 431, 'original_title' => 'Raccourci article', 'title' => 'Raccourci article', 'slug' => 'raccourci-article', 'legacy_url' => '/raccourci-article', 'status' => 'published']);
        $page = Page::create(['legacy_wp_id' => 432, 'original_title' => 'Raccourci page', 'title' => 'Raccourci page', 'slug' => 'raccourci-page', 'legacy_url' => '/raccourci-page', 'status' => 'published']);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $this->get('/resto/raccourci-restaurant')->assertOk()->assertDontSee('Modifier cette page dans l’administration');
        $this->actingAs(User::factory()->create())->get('/resto/raccourci-restaurant')->assertOk()->assertDontSee('Modifier cette page dans l’administration');

        $this->actingAs($admin)->get('/resto/raccourci-restaurant')
            ->assertOk()
            ->assertSee('Modifier cette page dans l’administration', false)
            ->assertSee(\App\Filament\Resources\RestaurantResource::getUrl('edit', ['record' => $restaurant]), false);
        $this->actingAs($admin)->get('/raccourci-article')
            ->assertOk()
            ->assertSee(\App\Filament\Resources\ArticleResource::getUrl('edit', ['record' => $article]), false);
        $this->actingAs($admin)->get('/raccourci-page')
            ->assertOk()
            ->assertSee(\App\Filament\Resources\PageResource::getUrl('edit', ['record' => $page]), false);
    }

    public function test_directory_filters_real_v2_relations_and_is_noindex_when_filtered(): void
    {
        $restaurant = Restaurant::create(['legacy_wp_id' => 411, 'name' => 'Le Safran', 'slug' => 'le-safran', 'status' => 'published', 'city_name' => 'Lyon']);
        $category = Category::create(['legacy_term_id' => 12, 'name' => 'Marocain', 'slug' => 'marocain']);
        $feature = Feature::create(['legacy_term_id' => 13, 'name' => 'À emporter', 'slug' => 'a-emporter']);
        $location = Location::create(['legacy_term_id' => 14, 'name' => 'Lyon', 'slug' => 'lyon']);
        $restaurant->categories()->attach($category);
        $restaurant->features()->attach($feature);
        $restaurant->locations()->attach($location);
        $this->get('/restaurants?q=safran&ville=lyon&categories[]=marocain&features[]=a-emporter')->assertOk()->assertSee('Le Safran')->assertSee('noindex,follow', false);
    }

    public function test_near_me_uses_only_non_null_coordinates_within_valid_ranges(): void
    {
        Restaurant::create(['legacy_wp_id' => 510, 'name' => 'Coordonnées valides', 'slug' => 'coordonnees-valides', 'status' => 'published', 'latitude' => 48.8566, 'longitude' => 2.3522]);
        Restaurant::create(['legacy_wp_id' => 511, 'name' => 'Latitude invalide', 'slug' => 'latitude-invalide', 'status' => 'published', 'latitude' => 91, 'longitude' => 2.3522]);
        Restaurant::create(['legacy_wp_id' => 512, 'name' => 'Longitude invalide', 'slug' => 'longitude-invalide', 'status' => 'published', 'latitude' => 48.8566, 'longitude' => 181]);
        Restaurant::create(['legacy_wp_id' => 513, 'name' => 'Sans coordonnées', 'slug' => 'sans-coordonnees', 'status' => 'published']);

        $this->get('/restaurants?lat=48.8566&lng=2.3522')
            ->assertOk()
            ->assertSee('Coordonnées valides')
            ->assertDontSee('Latitude invalide')
            ->assertDontSee('Longitude invalide')
            ->assertDontSee('Sans coordonnées');
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
        $restaurant = Restaurant::create(['legacy_wp_id' => 413, 'name' => "Adam's Burger", 'slug' => 'adams-burger', 'status' => 'published', 'city_name' => 'Lyon']);
        $category = Category::create(['legacy_term_id' => 413, 'name' => 'Marocain', 'slug' => 'marocain']);
        $restaurant->categories()->attach($category);

        $this->get('/resto/adams-burger')->assertOk()->assertSee('<title>Restaurant Adam&#039;s Burger Halal à Lyon spécialité Marocain</title>', false)->assertDontSee('Top Halal</title>', false)->assertDontSee('Adam&amp;#039;s Burger', false);
    }

    public function test_restaurant_address_uses_only_structured_fields_when_available(): void
    {
        Restaurant::create([
            'legacy_wp_id' => 415,
            'name' => 'Adresse structurée',
            'slug' => 'adresse-structuree',
            'status' => 'published',
            'address' => 'Adresse brute qui ne doit jamais être publique, 99999 Nullepart',
            'address_line1' => '54 Boulevard de La libération',
            'postal_code' => '13001',
            'city_name' => 'Marseille',
            'country_code' => 'FR',
        ]);

        $this->get('/resto/adresse-structuree')
            ->assertOk()
            ->assertSee('54 Boulevard de La libération')
            ->assertSee('13001 Marseille')
            ->assertDontSee('Adresse brute qui ne doit jamais être publique');
    }

    public function test_manual_restaurant_seo_title_overrides_the_default(): void
    {
        Restaurant::create(['legacy_wp_id' => 426, 'name' => 'Titre test', 'slug' => 'titre-test', 'status' => 'published', 'seo_title' => 'Titre personnalisé']);

        $this->get('/resto/titre-test')->assertOk()->assertSee('<title>Titre personnalisé</title>', false);
    }

    public function test_every_dynamic_public_page_title_is_escaped_once(): void
    {
        $restaurant = Restaurant::create(['legacy_wp_id' => 414, 'name' => "Adam's Burger", 'slug' => 'adams-burger', 'status' => 'published']);
        $category = Category::create(['legacy_term_id' => 15, 'name' => "Cuisine d'Orient", 'slug' => 'orient']);
        $feature = Feature::create(['legacy_term_id' => 16, 'name' => "Chef d'œuvre", 'slug' => 'chef-oeuvre']);
        $location = Location::create(['legacy_term_id' => 17, 'name' => "L'Haÿ-les-Roses", 'slug' => 'lhay']);
        $restaurant->categories()->attach($category);
        $restaurant->features()->attach($feature);
        $restaurant->locations()->attach($location);
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

    public function test_public_reviews_and_comments_are_sorted_from_newest_to_oldest(): void
    {
        $restaurant = Restaurant::create(['legacy_wp_id' => 427, 'name' => 'Ordre avis', 'slug' => 'ordre-avis', 'status' => 'published']);
        RestaurantReview::create(['restaurant_id' => $restaurant->id, 'author_name' => 'Amina', 'rating' => 5, 'content' => 'Ancien avis', 'status' => 'approved', 'created_at' => '2024-01-01 12:00:00']);
        RestaurantReview::create(['restaurant_id' => $restaurant->id, 'author_name' => 'Yanis', 'rating' => 4, 'content' => 'Nouvel avis', 'status' => 'approved', 'created_at' => '2024-02-01 12:00:00']);
        $article = Article::create(['legacy_wp_id' => 428, 'original_title' => 'Ordre commentaires', 'title' => 'Ordre commentaires', 'slug' => 'ordre-commentaires', 'legacy_url' => '/ordre-commentaires', 'status' => 'published']);
        Comment::create(['article_id' => $article->id, 'author_name' => 'Amina', 'content' => 'Ancien commentaire', 'status' => 'approved', 'created_at' => '2024-01-01 12:00:00']);
        Comment::create(['article_id' => $article->id, 'author_name' => 'Yanis', 'content' => 'Nouveau commentaire', 'status' => 'approved', 'created_at' => '2024-02-01 12:00:00']);

        $this->get('/resto/ordre-avis')->assertOk()->assertSeeInOrder(['Nouvel avis', 'Ancien avis']);
        $this->get('/ordre-commentaires')->assertOk()->assertSeeInOrder(['Nouveau commentaire', 'Ancien commentaire']);
    }

    public function test_article_detail_renders_its_featured_media(): void
    {
        $article = Article::create(['legacy_wp_id' => 422, 'original_title' => 'Article illustré', 'title' => 'Article illustré', 'slug' => 'article-illustre', 'legacy_url' => '/article-illustre', 'status' => 'published']);
        $asset = MediaAsset::create(['legacy_attachment_id' => 422, 'original_path' => 'media/originals/article.jpg', 'mime' => 'image/jpeg', 'width' => 1200, 'height' => 800, 'bytes' => 10, 'checksum' => str_repeat('c', 64), 'status' => 'ready']);
        ContentMedia::create(['content_type' => 'post', 'content_id' => $article->id, 'legacy_attachment_id' => 422, 'media_asset_id' => $asset->id, 'role' => 'featured']);

        $this->get('/article-illustre')->assertOk()->assertSee('article-featured-media', false)->assertSee($asset->deliveryUrl(), false);
    }

    public function test_restaurant_detail_falls_back_to_its_available_hero_variant(): void
    {
        $restaurant = Restaurant::create(['legacy_wp_id' => 423, 'name' => 'Restaurant illustré', 'slug' => 'restaurant-illustre', 'status' => 'published']);
        $asset = MediaAsset::create(['legacy_attachment_id' => 423, 'original_path' => 'media/originals/restaurant.jpg', 'mime' => 'image/jpeg', 'width' => 800, 'height' => 1200, 'bytes' => 10, 'checksum' => str_repeat('d', 64), 'status' => 'ready']);
        $asset->variants()->create(['format' => 'webp', 'width' => 480, 'height' => 720, 'path' => 'media/variants/restaurant-480.webp']);
        RestaurantMedia::create(['restaurant_id' => $restaurant->id, 'legacy_attachment_id' => 423, 'sort_order' => 0, 'status' => 'ready']);

        $this->get('/resto/restaurant-illustre')->assertOk()->assertSee($asset->deliveryUrl(480), false)->assertDontSee($asset->deliveryUrl(960), false);
    }

    public function test_restaurant_detail_uses_the_first_image_when_a_video_precedes_it(): void
    {
        $restaurant = Restaurant::create(['legacy_wp_id' => 424, 'name' => 'Restaurant sans vidéo', 'slug' => 'restaurant-sans-video', 'status' => 'published']);
        $video = MediaAsset::create(['legacy_attachment_id' => 424, 'original_path' => 'media/originals/video.mp4', 'mime' => 'video/mp4', 'bytes' => 10, 'checksum' => str_repeat('v', 64), 'status' => 'ready']);
        $image = MediaAsset::create(['legacy_attachment_id' => 425, 'original_path' => 'media/originals/image.jpg', 'mime' => 'image/jpeg', 'width' => 800, 'height' => 600, 'bytes' => 10, 'checksum' => str_repeat('g', 64), 'status' => 'ready']);
        $image->variants()->create(['format' => 'webp', 'width' => 480, 'height' => 360, 'path' => 'media/variants/image-480.webp']);
        RestaurantMedia::create(['restaurant_id' => $restaurant->id, 'media_asset_id' => $video->id, 'sort_order' => 0, 'status' => 'ready']);
        RestaurantMedia::create(['restaurant_id' => $restaurant->id, 'media_asset_id' => $image->id, 'sort_order' => 1, 'status' => 'ready']);

        $this->get('/resto/restaurant-sans-video')->assertOk()->assertSee($image->deliveryUrl(480), false)->assertDontSee($video->deliveryUrl(), false);
    }

    public function test_restaurant_gallery_falls_back_to_the_original_when_no_variant_exists(): void
    {
        $restaurant = Restaurant::create(['legacy_wp_id' => 426, 'name' => 'Galerie illustrée', 'slug' => 'galerie-illustree', 'status' => 'published']);
        $hero = MediaAsset::create(['legacy_attachment_id' => 426, 'original_path' => 'media/originals/hero.jpg', 'mime' => 'image/jpeg', 'width' => 800, 'height' => 600, 'bytes' => 10, 'checksum' => str_repeat('e', 64), 'status' => 'ready']);
        $gallery = MediaAsset::create(['legacy_attachment_id' => 427, 'original_path' => 'media/originals/gallery.jpg', 'mime' => 'image/jpeg', 'width' => 800, 'height' => 600, 'bytes' => 10, 'checksum' => str_repeat('f', 64), 'status' => 'ready']);
        RestaurantMedia::create(['restaurant_id' => $restaurant->id, 'legacy_attachment_id' => 426, 'media_asset_id' => $hero->id, 'sort_order' => 0, 'status' => 'ready']);
        RestaurantMedia::create(['restaurant_id' => $restaurant->id, 'legacy_attachment_id' => 427, 'media_asset_id' => $gallery->id, 'sort_order' => 1, 'status' => 'ready']);

        $this->get('/resto/galerie-illustree')->assertOk()->assertSee($gallery->deliveryUrl(), false)->assertDontSee($gallery->deliveryUrl(480), false);
    }

    public function test_restaurant_services_render_local_svg_icons_with_their_text_labels(): void
    {
        $restaurant = Restaurant::create(['legacy_wp_id' => 424, 'name' => 'Services test', 'slug' => 'services-test', 'status' => 'published']);
        $services = collect(['Accès handicapé', 'Ambiance musicale', 'Beau décor', 'Branché', 'Certifié halal', 'Original', 'Romantique', 'Salle de prière', 'Sans alcool', 'Terrasse', 'Traiteur', 'Vente à emporter', 'Wi-Fi'])
            ->map(fn (string $name, int $index) => Feature::create(['legacy_term_id' => 500 + $index, 'name' => $name, 'slug' => Str::slug($name)]));
        $restaurant->features()->attach($services->pluck('id'));

        $response = $this->get('/resto/services-test');

        $response->assertOk()->assertSee('Services')->assertSee('service-list', false)->assertSee('viewBox="0 0 24 24"', false);
        foreach ($services as $service) {
            $response->assertSee($service->name);
        }
    }

    public function test_restaurant_detail_renders_validated_opening_hours(): void
    {
        $restaurant = Restaurant::create(['legacy_wp_id' => 425, 'name' => 'Horaires test', 'slug' => 'horaires-test', 'status' => 'published']);
        RestaurantOpeningHour::create(['restaurant_id' => $restaurant->id, 'day' => 'monday', 'opens_at' => '11:15', 'closes_at' => '14:30', 'legacy_key' => 'web-monday-lunch']);
        RestaurantOpeningHour::create(['restaurant_id' => $restaurant->id, 'day' => 'monday', 'opens_at' => '18:00', 'closes_at' => '22:30', 'legacy_key' => 'web-monday-dinner']);
        RestaurantOpeningHour::create(['restaurant_id' => $restaurant->id, 'day' => 'sunday', 'is_closed' => true, 'legacy_key' => 'web-sunday-closed']);

        $this->get('/resto/horaires-test')
            ->assertOk()
            ->assertSee('Horaires')
            ->assertSee('Lundi')
            ->assertSee('11:15')
            ->assertSee('14:30')
            ->assertSee('18:00')
            ->assertSee('22:30')
            ->assertSee('Dimanche')
            ->assertSee('Fermé');
    }

    public function test_restaurant_detail_renders_the_server_calculated_opening_status(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-31 19:00', 'Europe/Paris'));

        try {
            $restaurant = Restaurant::create(['legacy_wp_id' => 426, 'name' => 'Statut horaires test', 'slug' => 'statut-horaires-test', 'status' => 'published']);
            RestaurantOpeningHour::create(['restaurant_id' => $restaurant->id, 'day' => 'monday', 'opens_at' => '10:00', 'closes_at' => '12:00', 'slot' => 1, 'legacy_key' => 'status-monday-lunch']);
            RestaurantOpeningHour::create(['restaurant_id' => $restaurant->id, 'day' => 'monday', 'opens_at' => '18:00', 'closes_at' => '23:00', 'slot' => 2, 'legacy_key' => 'status-monday-dinner']);

            $this->get('/resto/statut-horaires-test')
                ->assertOk()
                ->assertSee('opening-hours-card', false)
                ->assertSee('opening-hours-list', false)
                ->assertSee('is-today', false)
                ->assertSee('Ouvert actuellement · Ferme à 23:00');
        } finally {
            CarbonImmutable::setTestNow();
        }
    }
}
