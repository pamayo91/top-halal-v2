<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Feature;
use App\Models\LegacyRestaurantAuthorship;
use App\Models\MediaAsset;
use App\Models\Restaurant;
use App\Models\RestaurantClaim;
use App\Models\RestaurantMedia;
use App\Models\RestaurantSubmission;
use App\Models\User;
use App\Services\MediaIngestor;
use App\Services\RestaurantHours;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ManagedRestaurantEditorTest extends TestCase
{
    use DatabaseMigrations;

    public function test_each_supported_representative_uses_the_same_complete_editor(): void
    {
        foreach (['depositor', 'approved claimant', 'legacy author'] as $kind) {
            [$user, $restaurant] = $this->representedRestaurant($kind);

            $this->actingAs($user)->get(route('owner.restaurants.edit', $restaurant))
                ->assertOk()
                ->assertSee('Spécialités et services')
                ->assertSee('Horaires')
            ->assertSee('Photos')
            ->assertSee('Photos déjà enregistrées')
            ->assertSee('Nouvelles photos')
            ->assertSee('Votre adresse e-mail se gère uniquement')
            ->assertSee('data-hours-editor', false)
            ->assertSee('data-owner-new-photos-input', false)
            ->assertSee('+ Ajouter une plage')
            ->assertDontSee('data-owner-hours', false)
            ->assertDontSee('name="contact_email"', false);
        }
    }

    public function test_published_new_submission_owner_uses_the_complete_shared_editor(): void
    {
        [$owner, $restaurant] = $this->representedRestaurant('new submission owner');
        $restaurant->categories()->attach($this->category('Spécialité du propriétaire', 41));
        $restaurant->features()->attach($this->feature('Service du propriétaire', 42));

        $this->assertDatabaseHas('restaurant_claims', [
            'restaurant_id' => $restaurant->id,
            'user_id' => $owner->id,
            'source' => 'new_submission',
            'status' => 'approved',
        ]);
        $this->assertTrue($owner->can('manage', $restaurant));

        $this->actingAs($owner)->get(route('owner.restaurants.edit', $restaurant))
            ->assertOk()
            ->assertSee('Spécialités et services')
            ->assertSee('Horaires')
            ->assertSee('Adresse et localisation')
            ->assertSee('Contact et réseaux sociaux')
            ->assertSee('Photos')
            ->assertSee('name="categories[]"', false)
            ->assertSee('name="features[]"', false)
            ->assertSee('name="hours[0][status]"', false)
            ->assertSee('name="location_changed"', false)
            ->assertSee('name="website_url"', false)
            ->assertSee('name="new_photos[]"', false)
            ->assertDontSee('name="contact_email"', false);

        $this->actingAs($owner)->put(route('owner.restaurants.update', $restaurant), [
            'name' => 'Restaurant du nouveau propriétaire',
        ])->assertRedirect();

        $this->assertSame('Restaurant du nouveau propriétaire', $restaurant->fresh()->name);
    }

    public function test_a_manager_updates_business_data_media_hours_links_and_gps_without_touching_administrative_fields(): void
    {
        [$manager, $restaurant] = $this->representedRestaurant('approved claimant');
        $initialCategory = $this->category('Initiale', 11);
        $newCategory = $this->category('Nouvelle spécialité', 12);
        $initialFeature = $this->feature('Initial', 21);
        $newFeature = $this->feature('Nouveau service', 22);
        $restaurant->categories()->attach($initialCategory);
        $restaurant->features()->attach($initialFeature);
        $restaurant->openingHours()->create(['day' => 'monday', 'slot' => 1, 'opens_at' => '11:00', 'closes_at' => '14:00', 'legacy_key' => 'legacy:monday:1']);
        $first = $this->media($restaurant, 'a');
        $second = $this->media($restaurant, 'b');
        $restaurant->outboundLinks()->create(['token' => str_repeat('w', 40), 'label' => 'Site web', 'destination_url' => 'https://old.example.test', 'is_active' => true]);

        $firstNewAsset = MediaAsset::create(['original_path' => 'media/originals/new-one.jpg', 'mime' => 'image/jpeg', 'width' => 1200, 'height' => 800, 'bytes' => 10, 'checksum' => str_repeat('c', 64), 'status' => 'ready']);
        $secondNewAsset = MediaAsset::create(['original_path' => 'media/originals/new-two.jpg', 'mime' => 'image/jpeg', 'width' => 1200, 'height' => 800, 'bytes' => 10, 'checksum' => str_repeat('e', 64), 'status' => 'ready']);
        $ingestor = \Mockery::mock(MediaIngestor::class);
        $ingestor->shouldReceive('ingest')->twice()->with(\Mockery::type(UploadedFile::class), 'Restaurant entièrement modifié')->andReturn($firstNewAsset, $secondNewAsset);
        $this->app->instance(MediaIngestor::class, $ingestor);

        $hours = app(RestaurantHours::class)->editorState($restaurant->openingHours()->get());
        $hours[0]['slots'] = [
            ['id' => $hours[0]['slots'][0]['id'], 'opens_at' => '10:30', 'closes_at' => '14:30'],
            ['opens_at' => '18:00', 'closes_at' => '22:30'],
        ];
        $payload = [
            'name' => 'Restaurant entièrement modifié',
            'description' => 'Une description éditée sans lien.',
            'phone' => '+33 1 23 45 67 89',
            'halal_meat' => '1',
            'halal_chicken' => '0',
            'categories' => [$newCategory->id],
            'features' => [$newFeature->id],
            'hours' => $hours,
            'location_changed' => '0',
            'map_moved' => '1',
            'latitude' => '48.8566000',
            'longitude' => '2.3522000',
            'remove_media_ids' => [$second->id],
            'media_order' => [$first->id, $second->id],
            'new_photos' => [
                UploadedFile::fake()->image('nouvelle-photo-un.jpg', 1200, 800),
                UploadedFile::fake()->image('nouvelle-photo-deux.jpg', 1200, 800),
            ],
            'website_url' => 'https://new.example.test',
            'instagram_url' => 'https://instagram.com/tophalaltest',
            'facebook_url' => '',
            'tiktok_url' => '',
        ];

        $this->actingAs($manager)->put(route('owner.restaurants.update', $restaurant), $payload)->assertRedirect();

        $updated = $restaurant->fresh();
        $this->assertSame('Restaurant entièrement modifié', $updated->name);
        $this->assertSame('Une description éditée sans lien.', $updated->description);
        $this->assertSame('+33 1 23 45 67 89', $updated->phone);
        $this->assertTrue($updated->has_halal_meat);
        $this->assertFalse($updated->has_halal_chicken);
        $this->assertSame([$newCategory->id], $updated->categories()->pluck('categories.id')->all());
        $this->assertSame([$newFeature->id], $updated->features()->pluck('features.id')->all());
        $this->assertSame('10:30', substr((string) $updated->openingHours()->where('day', 'monday')->orderBy('slot')->first()->opens_at, 0, 5));
        $this->assertCount(2, $updated->openingHours()->where('day', 'monday')->get());
        $this->assertSame('48.8566000', (string) $updated->latitude);
        $this->assertSame('2.3522000', (string) $updated->longitude);
        $this->assertDatabaseMissing('restaurant_media', ['id' => $second->id]);
        $this->assertCount(3, $updated->media()->where('role', '!=', 'fallback_thumbnail')->get());
        $this->assertDatabaseHas('restaurant_media', ['restaurant_id' => $restaurant->id, 'media_asset_id' => $firstNewAsset->id]);
        $this->assertDatabaseHas('restaurant_media', ['restaurant_id' => $restaurant->id, 'media_asset_id' => $secondNewAsset->id]);
        $this->assertDatabaseHas('restaurant_outbound_links', ['restaurant_id' => $restaurant->id, 'label' => 'Site web', 'destination_url' => 'https://new.example.test']);
        $this->assertDatabaseHas('restaurant_outbound_links', ['restaurant_id' => $restaurant->id, 'label' => 'Instagram', 'is_active' => false]);
        $this->assertSame('published', $updated->status);
        $this->assertSame('restaurant-represente-approved-claimant', $updated->slug);
        $this->assertSame('account@example.test', $updated->contact_email);
    }

    public function test_a_former_depositor_cannot_alter_a_restaurant_after_an_approved_claim(): void
    {
        [$depositor, $restaurant] = $this->representedRestaurant('depositor');
        $newManager = User::factory()->create(['role' => 'user']);
        RestaurantClaim::create(['restaurant_id' => $restaurant->id, 'user_id' => $newManager->id, 'status' => 'approved', 'submitted_at' => now()]);

        $this->actingAs($depositor)->put(route('owner.restaurants.update', $restaurant), ['name' => 'Modification refusée'])
            ->assertRedirect(route('owner.restaurants.management-unavailable', $restaurant));
        $this->assertSame('Restaurant représenté', $restaurant->fresh()->name);
    }

    public function test_a_partial_update_does_not_change_unrequested_business_relations_or_contact_data(): void
    {
        [$manager, $restaurant] = $this->representedRestaurant('depositor');
        $category = $this->category('À conserver', 31);
        $feature = $this->feature('À conserver', 32);
        $restaurant->categories()->attach($category);
        $restaurant->features()->attach($feature);
        $hour = $restaurant->openingHours()->create(['day' => 'monday', 'slot' => 1, 'opens_at' => '11:00', 'closes_at' => '14:00', 'legacy_key' => 'legacy:monday:1']);
        $media = $this->media($restaurant, 'd');
        $restaurant->outboundLinks()->create(['token' => str_repeat('k', 40), 'label' => 'Site web', 'destination_url' => 'https://kept.example.test', 'is_active' => true]);

        $this->actingAs($manager)->put(route('owner.restaurants.update', $restaurant), ['name' => 'Nom uniquement modifié'])->assertRedirect();

        $this->assertSame('Nom uniquement modifié', $restaurant->fresh()->name);
        $this->assertSame([$category->id], $restaurant->categories()->pluck('categories.id')->all());
        $this->assertSame([$feature->id], $restaurant->features()->pluck('features.id')->all());
        $this->assertDatabaseHas('restaurant_opening_hours', ['id' => $hour->id, 'opens_at' => '11:00']);
        $this->assertDatabaseHas('restaurant_media', ['id' => $media->id]);
        $this->assertDatabaseHas('restaurant_outbound_links', ['restaurant_id' => $restaurant->id, 'destination_url' => 'https://kept.example.test']);
        $this->assertSame('account@example.test', $restaurant->fresh()->contact_email);
    }

    public function test_a_manager_cannot_bypass_the_existing_photo_dimensions_rule(): void
    {
        [$manager, $restaurant] = $this->representedRestaurant('depositor');

        $this->actingAs($manager)->put(route('owner.restaurants.update', $restaurant), [
            'name' => $restaurant->name,
            'new_photos' => [UploadedFile::fake()->image('trop-petite.jpg', 799, 600)],
        ])->assertSessionHasErrors('new_photos.0');

        $this->assertCount(0, $restaurant->media()->where('role', '!=', 'fallback_thumbnail')->get());
    }

    public function test_the_complete_editor_requires_a_specialty_and_a_service_server_side(): void
    {
        [$manager, $restaurant] = $this->representedRestaurant('depositor');

        $this->actingAs($manager)->put(route('owner.restaurants.update', $restaurant), [
            'name' => 'Restaurant représenté',
            'editor_complete' => '1',
            'halal_meat' => '1',
            'halal_chicken' => '0',
        ])->assertSessionHasErrors(['categories', 'features']);
    }

    public function test_preselected_taxonomies_do_not_leave_an_unchecked_checkbox_required(): void
    {
        [$manager, $restaurant] = $this->representedRestaurant('depositor');
        $firstCategory = $this->category('Africaine', 501);
        $selectedCategory = $this->category('Zoulou', 502);
        $firstFeature = $this->feature('Accès handicapé', 503);
        $selectedFeature = $this->feature('Wi-Fi', 504);
        $restaurant->categories()->attach($selectedCategory);
        $restaurant->features()->attach($selectedFeature);

        $html = $this->actingAs($manager)->get(route('owner.restaurants.edit', $restaurant))->assertOk()->getContent();

        $this->assertDoesNotMatchRegularExpression('/name="categories\[\]"\s+value="'.$firstCategory->id.'"[^>]*\srequired/', $html);
        $this->assertDoesNotMatchRegularExpression('/name="features\[\]"\s+value="'.$firstFeature->id.'"[^>]*\srequired/', $html);
        $this->assertMatchesRegularExpression('/name="categories\[\]"\s+value="'.$selectedCategory->id.'"[^>]*checked/', $html);
        $this->assertMatchesRegularExpression('/name="features\[\]"\s+value="'.$selectedFeature->id.'"[^>]*checked/', $html);
    }

    public function test_the_existing_photo_controls_defer_removal_and_hide_unavailable_reordering_actions(): void
    {
        [$manager, $restaurant] = $this->representedRestaurant('depositor');
        $cover = $this->media($restaurant, 'm');
        $gallery = $this->media($restaurant, 'n');

        $html = $this->actingAs($manager)->get(route('owner.restaurants.edit', $restaurant))->assertOk()->getContent();

        $this->assertStringNotContainsString('Retirer cette photo', $html);
        $this->assertStringContainsString('name="remove_media_ids[]" value="'.$cover->id.'" disabled data-owner-media-remove-input', $html);
        $this->assertStringContainsString('Cette photo sera retirée à l’enregistrement', $html);
        $this->assertStringContainsString('>Annuler</button>', $html);
        $this->assertStringContainsString('JPEG, PNG ou WebP · 800 px minimum · 10 Mo maximum', $html);
        $this->assertStringContainsString('10 photos maximum · Envoi uniquement à l’enregistrement', $html);
        $this->assertMatchesRegularExpression('/data-media-id="'.$cover->id.'".*?data-owner-media-up\s+hidden.*?data-owner-media-down\s*>(?!<)/s', $html);
        $this->assertMatchesRegularExpression('/data-media-id="'.$gallery->id.'".*?data-owner-media-up\s*>(?!<).*?data-owner-media-down\s+hidden/s', $html);
    }

    public function test_a_single_existing_photo_has_no_reordering_actions(): void
    {
        [$manager, $restaurant] = $this->representedRestaurant('depositor');
        $cover = $this->media($restaurant, 'o');

        $html = $this->actingAs($manager)->get(route('owner.restaurants.edit', $restaurant))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/data-media-id="'.$cover->id.'".*?data-owner-media-up\s+hidden.*?data-owner-media-down\s+hidden/s', $html);
    }

    /** @return array{User, Restaurant} */
    private function representedRestaurant(string $kind): array
    {
        $restaurant = Restaurant::create([
            'legacy_wp_id' => random_int(100000, 999999),
            'name' => 'Restaurant représenté',
            'slug' => 'restaurant-represente-'.str($kind)->slug(),
            'status' => 'published',
            'contact_email' => 'account@example.test',
            'address_line1' => '1 rue initiale',
            'postal_code' => '75001',
            'city_name' => 'Paris',
            'latitude' => 48.8566,
            'longitude' => 2.3522,
        ]);
        $user = User::factory()->create(['role' => 'user']);
        if ($kind === 'depositor') RestaurantSubmission::create(['restaurant_id' => $restaurant->id, 'user_id' => $user->id, 'submitter_email' => $user->email, 'submitter_role' => 'customer', 'status' => 'published', 'submitted_at' => now()]);
        if ($kind === 'approved claimant') RestaurantClaim::create(['restaurant_id' => $restaurant->id, 'user_id' => $user->id, 'status' => 'approved', 'submitted_at' => now()]);
        if ($kind === 'new submission owner') {
            RestaurantSubmission::create(['restaurant_id' => $restaurant->id, 'user_id' => $user->id, 'submitter_email' => $user->email, 'submitter_role' => 'owner', 'status' => 'published', 'submitted_at' => now()]);
            RestaurantClaim::create(['restaurant_id' => $restaurant->id, 'user_id' => $user->id, 'source' => 'new_submission', 'status' => 'approved', 'submitted_at' => now()]);
        }
        if ($kind === 'legacy author') LegacyRestaurantAuthorship::create(['restaurant_id' => $restaurant->id, 'user_id' => $user->id, 'legacy_wp_id' => $restaurant->legacy_wp_id, 'legacy_wp_user_id' => random_int(1000, 9999), 'source_post_status' => 'publish']);

        return [$user, $restaurant];
    }

    private function category(string $name, int $legacyId): Category
    {
        return Category::create(['legacy_term_id' => $legacyId, 'name' => $name, 'slug' => str($name)->slug().'-'.$legacyId]);
    }

    private function feature(string $name, int $legacyId): Feature
    {
        return Feature::create(['legacy_term_id' => $legacyId, 'name' => $name, 'slug' => str($name)->slug().'-'.$legacyId]);
    }

    private function media(Restaurant $restaurant, string $checksumCharacter): RestaurantMedia
    {
        $asset = MediaAsset::create(['original_path' => "media/originals/$checksumCharacter.jpg", 'mime' => 'image/jpeg', 'width' => 1200, 'height' => 800, 'bytes' => 10, 'checksum' => str_repeat($checksumCharacter, 64), 'status' => 'ready']);

        return RestaurantMedia::create(['restaurant_id' => $restaurant->id, 'media_asset_id' => $asset->id, 'sort_order' => RestaurantMedia::where('restaurant_id', $restaurant->id)->count(), 'status' => 'ready', 'role' => 'gallery']);
    }
}
