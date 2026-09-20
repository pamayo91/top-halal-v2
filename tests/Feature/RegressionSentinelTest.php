<?php

namespace Tests\Feature;

use App\Models\{Article, Category, Feature, MediaAsset, Page, RedirectRule, RegressionSentinel, Restaurant, RestaurantMedia, RestaurantReview};
use App\Services\Regression\SentinelRegistry;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RegressionSentinelTest extends TestCase
{
    use DatabaseMigrations;

    public function test_registered_sentinels_verify_database_media_and_relations(): void
    {
        Storage::fake('local');
        $this->makeRepresentativeData();

        $this->artisan('regression:sentinels', ['--refresh-baseline' => true])->assertSuccessful();
        $this->artisan('regression:verify')->assertSuccessful();
    }

    public function test_regression_verification_fails_when_an_unrelated_edit_would_have_lost_a_relation(): void
    {
        Storage::fake('local');
        $restaurant = $this->makeRepresentativeData();
        $this->artisan('regression:sentinels', ['--refresh-baseline' => true])->assertSuccessful();

        $restaurant->categories()->detach();

        $this->artisan('regression:verify')->assertFailed();
    }

    public function test_a_non_location_restaurant_edit_preserves_registered_relations_and_media(): void
    {
        Storage::fake('local');
        $restaurant = $this->makeRepresentativeData();
        $this->artisan('regression:sentinels', ['--refresh-baseline' => true])->assertSuccessful();

        app(\App\Services\Location\RestaurantLocationService::class)->update($restaurant, ['description' => 'Texte édité uniquement']);

        $this->artisan('regression:verify')->assertSuccessful();
    }

    public function test_live_reviews_do_not_affect_category_media_or_address_sentinels(): void
    {
        $sentinels = $this->registerRestaurantSentinels();

        foreach (['restaurant.categories', 'restaurant.single_media', 'restaurant.structured_address'] as $key) {
            RestaurantReview::create([
                'restaurant_id' => $sentinels[$key], 'author_name' => 'Nouvel avis', 'rating' => 4,
                'content' => 'Contribution publique légitime.', 'status' => 'pending',
            ]);
        }

        self::assertSame([], app(SentinelRegistry::class)->verify()['errors']);
    }

    public function test_review_sentinel_keeps_its_protected_review_without_rejecting_a_new_one(): void
    {
        $sentinels = $this->registerRestaurantSentinels();
        $restaurantId = $sentinels['restaurant.reviews'];
        $protectedReview = RestaurantReview::where('restaurant_id', $restaurantId)->sole();

        RestaurantReview::create([
            'restaurant_id' => $restaurantId, 'author_name' => 'Nouvel avis', 'rating' => 4,
            'content' => 'Contribution publique légitime.', 'status' => 'pending',
        ]);

        self::assertSame([], app(SentinelRegistry::class)->verify()['errors']);

        $otherRestaurant = Restaurant::create([
            'legacy_wp_id' => 900004, 'name' => 'Autre restaurant', 'slug' => 'autre-restaurant', 'status' => 'published',
        ]);
        $protectedReview->update(['restaurant_id' => $otherRestaurant->id]);

        self::assertContains(
            "restaurant.reviews: protected review #{$protectedReview->id} is missing or no longer belongs to the restaurant.",
            app(SentinelRegistry::class)->verify()['errors'],
        );
    }

    /** @return array<string, int> */
    private function registerRestaurantSentinels(): array
    {
        Storage::fake('local');
        $this->makeRepresentativeData();
        $singleMedia = Restaurant::create([
            'legacy_wp_id' => 900003, 'name' => 'Photo unique', 'slug' => 'photo-unique', 'status' => 'published',
        ]);
        RestaurantMedia::create([
            'restaurant_id' => $singleMedia->id, 'legacy_attachment_id' => 900003,
            'media_asset_id' => MediaAsset::where('legacy_attachment_id', 900001)->value('id'), 'status' => 'ready',
        ]);
        $this->artisan('regression:sentinels', ['--refresh-baseline' => true])->assertSuccessful();

        return RegressionSentinel::query()->whereIn('key', [
            'restaurant.categories', 'restaurant.single_media', 'restaurant.structured_address', 'restaurant.reviews',
        ])->get()->mapWithKeys(fn (RegressionSentinel $sentinel): array => [$sentinel->key => $sentinel->subject_id])->all();
    }

    private function makeRepresentativeData(): Restaurant
    {
        $restaurant = Restaurant::create([
            'legacy_wp_id' => 900001, 'name' => 'Sentinel restaurant', 'slug' => 'sentinel-restaurant', 'status' => 'published',
            'address_line1' => '1 rue Sentinelle', 'postal_code' => '75001', 'city_name' => 'Paris', 'city_code' => '75101', 'country_code' => 'FR', 'latitude' => 48.8600, 'longitude' => 2.3400,
        ]);
        $restaurantWithoutMedia = Restaurant::create(['legacy_wp_id' => 900002, 'name' => 'Sans photo', 'slug' => 'sentinel-sans-photo', 'status' => 'published']);
        unset($restaurantWithoutMedia);
        $category = Category::create(['legacy_term_id' => 900001, 'name' => 'Catégorie sentinelle', 'slug' => 'categorie-sentinelle']);
        $feature = Feature::create(['legacy_term_id' => 900001, 'name' => 'Service sentinelle', 'slug' => 'service-sentinelle']);
        $restaurant->categories()->attach($category);
        $restaurant->features()->attach($feature);
        RestaurantReview::create(['restaurant_id' => $restaurant->id, 'author_name' => 'Test', 'rating' => 5, 'content' => 'Avis sentinelle', 'status' => 'approved']);

        foreach ([900001, 900002] as $offset) {
            $path = "media/originals/sentinel-{$offset}.jpg";
            $variantPath = "media/variants/sentinel-{$offset}-480.webp";
            Storage::disk('local')->put($path, 'image');
            Storage::disk('local')->put($variantPath, 'image');
            $asset = MediaAsset::create(['legacy_attachment_id' => $offset, 'original_path' => $path, 'mime' => 'image/jpeg', 'width' => 800, 'height' => 600, 'bytes' => 5, 'checksum' => str_repeat((string) ($offset - 900000), 64), 'status' => 'ready']);
            $asset->variants()->create(['format' => 'webp', 'width' => 480, 'height' => 360, 'path' => $variantPath]);
            RestaurantMedia::create(['restaurant_id' => $restaurant->id, 'legacy_attachment_id' => $offset, 'media_asset_id' => $asset->id, 'sort_order' => $offset - 900001, 'status' => 'ready']);
        }

        $article = Article::create(['legacy_wp_id' => 900001, 'original_title' => 'Article sentinel', 'title' => 'Article sentinel', 'slug' => 'article-sentinel', 'legacy_url' => '/article-sentinel', 'status' => 'published', 'content_html' => '<p>Contenu</p>']);
        $articleWithoutMedia = Article::create(['legacy_wp_id' => 900002, 'original_title' => 'Article sans média', 'title' => 'Article sans média', 'slug' => 'article-sans-media', 'legacy_url' => '/article-sans-media', 'status' => 'published', 'content_html' => '<p>Contenu</p>']);
        unset($articleWithoutMedia);
        $article->contentMedia()->create(['content_type' => 'post', 'legacy_attachment_id' => 900001, 'media_asset_id' => MediaAsset::where('legacy_attachment_id', 900001)->value('id'), 'role' => 'featured']);
        $article->contentMedia()->create(['content_type' => 'post', 'legacy_attachment_id' => 900002, 'media_asset_id' => MediaAsset::where('legacy_attachment_id', 900002)->value('id'), 'role' => 'inline']);
        Page::create(['legacy_wp_id' => 900001, 'original_title' => 'Page sentinel', 'title' => 'Page sentinel', 'slug' => 'page-sentinelle', 'legacy_url' => '/page-sentinelle', 'status' => 'published']);
        RedirectRule::create(['source_path' => '/ancienne-sentinelle', 'match_type' => 'exact', 'destination' => '/', 'status_code' => 301, 'priority' => 1000, 'is_active' => true]);

        return $restaurant;
    }
}
