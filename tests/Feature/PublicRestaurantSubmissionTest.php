<?php

namespace Tests\Feature;

use App\Models\{Category, Feature, MediaAsset, Restaurant, RestaurantSubmission};
use App\Services\Geocoding\GeocodingService;
use App\Services\MediaIngestor;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Mockery;
use Tests\TestCase;

class PublicRestaurantSubmissionTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(GeocodingService::class, new class implements GeocodingService {
            public function search(string $query, int $limit = 3): array { return ['ok' => true, 'query' => $query, 'cached' => false, 'error' => null, 'features' => [['label' => '46 Boulevard du Temple 75011 Paris', 'postcode' => '75011', 'city' => 'Paris', 'citycode' => '75111', 'latitude' => 48.866, 'longitude' => 2.364, 'id' => 'BAN-46', 'type' => 'housenumber', 'score' => .92]]]; }
            public function reverse(float $latitude, float $longitude, int $limit = 3): array { return ['ok' => true, 'query' => '', 'cached' => false, 'error' => null, 'features' => []]; }
        });
    }

    public function test_form_is_public_and_never_indexable(): void
    {
        $this->get(route('restaurant-submissions.create'))
            ->assertOk()
            ->assertSee('Étape 1 sur 5')
            ->assertSee('Photo de couverture')
            ->assertSee('Votre adresse exacte n’apparaît pas ? Sélectionnez l’adresse la plus proche proposée, puis ajustez précisément la position du restaurant sur la carte.')
            ->assertDontSee('Code INSEE')
            ->assertSee('noindex,nofollow', false);
    }

    public function test_it_rejects_manual_address_fields_without_a_geoplateforme_selection(): void
    {
        $this->from(route('restaurant-submissions.create'))->post(route('restaurant-submissions.store'), $this->payload([
            'address_suggestion_token' => null,
            'address_line1' => '46 Boulevard du Temple',
            'postal_code' => '75011',
            'city_name' => 'Paris',
            'city_code' => '75111',
            'latitude' => 48.866,
            'longitude' => 2.364,
        ]))->assertRedirect(route('restaurant-submissions.create'))
            ->assertSessionHasErrors('address_suggestion_token');

        $this->assertDatabaseCount('restaurants', 0);
    }

    public function test_it_requires_one_halal_option(): void
    {
        $this->from(route('restaurant-submissions.create'))->post(route('restaurant-submissions.store'), $this->payload(['halal_meat' => null, 'halal_chicken' => null]))
            ->assertRedirect(route('restaurant-submissions.create'))
            ->assertSessionHasErrors('halal_meat');

        $this->assertDatabaseCount('restaurants', 0);
    }

    public function test_it_requires_a_cover_photo_and_a_valid_email(): void
    {
        $this->from(route('restaurant-submissions.create'))->post(route('restaurant-submissions.store'), $this->payload(['cover_photo' => null]))
            ->assertRedirect(route('restaurant-submissions.create'))
            ->assertSessionHasErrors('cover_photo');

        $this->from(route('restaurant-submissions.create'))->post(route('restaurant-submissions.store'), $this->payload(['email' => 'not-an-email']))
            ->assertRedirect(route('restaurant-submissions.create'))
            ->assertSessionHasErrors('email');
    }

    public function test_it_rejects_cover_and_gallery_images_narrower_than_800_pixels(): void
    {
        $this->from(route('restaurant-submissions.create'))->post(route('restaurant-submissions.store'), $this->payload([
            'cover_photo' => UploadedFile::fake()->image('couverture-trop-petite.jpg', 799, 600),
        ]))->assertRedirect(route('restaurant-submissions.create'))
            ->assertSessionHasErrors('cover_photo');

        $this->from(route('restaurant-submissions.create'))->post(route('restaurant-submissions.store'), $this->payload([
            'gallery_photos' => [UploadedFile::fake()->image('galerie-trop-petite.jpg', 799, 600)],
        ]))->assertRedirect(route('restaurant-submissions.create'))
            ->assertSessionHasErrors('gallery_photos.0');

        $this->assertDatabaseCount('restaurants', 0);
    }

    public function test_it_submits_a_complete_pending_restaurant_with_private_outbound_links(): void
    {
        $category = Category::create(['legacy_term_id' => 1001, 'name' => 'Libanais', 'slug' => 'libanais']);
        $feature = Feature::create(['legacy_term_id' => 1002, 'name' => 'Certification halal', 'slug' => 'certification-halal']);
        $asset = MediaAsset::create(['original_path' => 'media/originals/test.jpg', 'mime' => 'image/jpeg', 'width' => 800, 'height' => 600, 'bytes' => 100, 'checksum' => str_repeat('a', 64), 'status' => 'ready']);
        $ingestor = Mockery::mock(MediaIngestor::class);
        $ingestor->shouldReceive('ingest')->once()->andReturn($asset);
        $this->app->instance(MediaIngestor::class, $ingestor);

        $this->post(route('restaurant-submissions.store'), $this->payload([
            'categories' => [$category->id],
            'features' => [$feature->id],
            'website_url' => 'https://example.test/menu',
            'description' => 'Cuisine libanaise préparée sur place.',
        ]))->assertRedirect(route('restaurant-submissions.thanks'));

        $restaurant = Restaurant::firstOrFail();
        $this->assertNull($restaurant->legacy_wp_id);
        $this->assertSame('pending', $restaurant->status);
        $this->assertTrue($restaurant->has_halal_meat);
        $this->assertFalse($restaurant->has_halal_chicken);
        $this->assertSame('0123456789', $restaurant->phone);
        $this->assertSame('contributeur@example.invalid', $restaurant->contact_email);
        $this->assertSame('46 Boulevard du Temple', $restaurant->address_line1);
        $this->assertSame('75111', $restaurant->city_code);
        $this->assertSame('VERIFIED', $restaurant->geocoding_status);
        $this->assertTrue($restaurant->categories->contains($category));
        $this->assertTrue($restaurant->features->contains($feature));
        $this->assertCount(7, $restaurant->openingHours);
        $this->assertDatabaseHas('restaurant_media', ['restaurant_id' => $restaurant->id, 'media_asset_id' => $asset->id, 'sort_order' => 0]);
        $this->assertDatabaseHas('restaurant_outbound_links', ['restaurant_id' => $restaurant->id, 'destination_url' => 'https://example.test/menu', 'is_active' => 0]);
        $this->assertSame('customer', RestaurantSubmission::firstOrFail()->submitter_role);
    }

    public function test_a_marker_move_changes_only_coordinates_after_the_selected_address_is_persisted(): void
    {
        $asset = MediaAsset::create(['original_path' => 'media/originals/test.jpg', 'mime' => 'image/jpeg', 'width' => 800, 'height' => 600, 'bytes' => 100, 'checksum' => str_repeat('b', 64), 'status' => 'ready']);
        $ingestor = Mockery::mock(MediaIngestor::class);
        $ingestor->shouldReceive('ingest')->once()->andReturn($asset);
        $this->app->instance(MediaIngestor::class, $ingestor);

        $this->post(route('restaurant-submissions.store'), $this->payload([
            'map_moved' => '1',
            'latitude' => 48.867,
            'longitude' => 2.365,
        ]))->assertRedirect(route('restaurant-submissions.thanks'));

        $restaurant = Restaurant::firstOrFail();
        $this->assertSame('46 Boulevard du Temple', $restaurant->address_line1);
        $this->assertSame('75011', $restaurant->postal_code);
        $this->assertSame('Paris', $restaurant->city_name);
        $this->assertSame('75111', $restaurant->city_code);
        $this->assertSame('BAN-46', $restaurant->geocoding_source_id);
        $this->assertSame('48.8670000', $restaurant->latitude);
        $this->assertSame('2.3650000', $restaurant->longitude);
        $this->assertSame('VERIFIED', $restaurant->geocoding_status);
    }

    public function test_submission_never_modifies_an_existing_restaurant(): void
    {
        $existing = Restaurant::create(['legacy_wp_id' => 987, 'name' => 'Historique', 'slug' => 'historique', 'status' => 'published', 'address' => 'Adresse historique', 'address_line1' => '1 Rue Historique', 'postal_code' => '75001', 'city_name' => 'Paris', 'city_code' => '75101', 'latitude' => 48.8566, 'longitude' => 2.3522]);
        $before = $existing->only(['address', 'address_line1', 'postal_code', 'city_name', 'city_code', 'latitude', 'longitude']);
        $asset = MediaAsset::create(['original_path' => 'media/originals/test.jpg', 'mime' => 'image/jpeg', 'width' => 800, 'height' => 600, 'bytes' => 100, 'checksum' => str_repeat('c', 64), 'status' => 'ready']);
        $ingestor = Mockery::mock(MediaIngestor::class);
        $ingestor->shouldReceive('ingest')->once()->andReturn($asset);
        $this->app->instance(MediaIngestor::class, $ingestor);

        $this->post(route('restaurant-submissions.store'), $this->payload())->assertRedirect(route('restaurant-submissions.thanks'));

        $this->assertSame($before, $existing->fresh()->only(array_keys($before)));
    }

    public function test_address_endpoint_and_duplicate_endpoint_expose_only_the_safe_public_contract(): void
    {
        Restaurant::create(['legacy_wp_id' => 99, 'name' => 'Le Safran', 'slug' => 'le-safran', 'status' => 'published', 'address_line1' => '46 Boulevard du Temple', 'city_name' => 'Paris', 'latitude' => 48.866, 'longitude' => 2.364]);

        $this->getJson(route('restaurant-submissions.addresses', ['q' => 'Boulevard du Temple']))
            ->assertOk()
            ->assertJsonMissing(['city_code' => '75111'])
            ->assertJsonMissing(['geocoding_source_id' => 'BAN-46']);

        $this->getJson(route('restaurant-submissions.duplicates', ['name' => 'Le Safran', 'address_line1' => '46 Boulevard du Temple', 'city_name' => 'Paris', 'latitude' => 48.866, 'longitude' => 2.364]))
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Le Safran')
            ->assertJsonPath('data.0.url', route('restaurants.show', 'le-safran'));
    }

    private function payload(array $overrides = []): array
    {
        $hours = [];
        foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day) $hours[$day] = ['status' => 'closed'];

        return array_replace_recursive([
            'name' => 'Restaurant de test',
            'halal_meat' => '1',
            'halal_chicken' => '0',
            'address_suggestion_token' => app(\App\Services\Location\AddressSuggestionService::class)->suggest('46 boulevard du temple')[0]['token'],
            'hours' => $hours,
            'cover_photo' => UploadedFile::fake()->image('cover.jpg', 800, 600),
            'phone' => '0123456789',
            'submitter_role' => 'customer',
            'email' => 'contributeur@example.invalid',
        ], $overrides);
    }
}
