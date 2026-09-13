<?php

namespace Tests\Feature;

use App\Mail\TemplateMailable;
use App\Models\{Category, Feature, MediaAsset, Restaurant, RestaurantClaim, RestaurantReview, RestaurantSubmission, Setting, User};
use App\Services\Geocoding\GeocodingService;
use App\Services\MediaIngestor;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Hash, Mail, Password, URL};
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class PublicRestaurantSubmissionTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
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
        $this->assertSame('FR', $restaurant->country_code);
        $this->assertSame('48.8660000', $restaurant->latitude);
        $this->assertSame('2.3640000', $restaurant->longitude);
        $this->assertTrue($restaurant->categories->contains($category));
        $this->assertTrue($restaurant->features->contains($feature));
        $this->assertCount(7, $restaurant->openingHours);
        $this->assertDatabaseHas('restaurant_media', ['restaurant_id' => $restaurant->id, 'media_asset_id' => $asset->id, 'sort_order' => 0]);
        $this->assertDatabaseHas('restaurant_outbound_links', ['restaurant_id' => $restaurant->id, 'destination_url' => 'https://example.test/menu', 'is_active' => 0]);
        $this->assertSame('customer', RestaurantSubmission::firstOrFail()->submitter_role);
        $submission = RestaurantSubmission::firstOrFail();
        $this->assertSame('pending_email_verification', $submission->status);
        $this->assertNull($submission->email_verified_at);
        $this->assertNotNull($submission->email_verification_token);
        $this->assertDatabaseHas('email_delivery_logs', ['template_key' => 'restaurant_submission_email_verification', 'recipient' => 'contributeur@example.invalid']);
        Mail::assertQueued(TemplateMailable::class, fn (TemplateMailable $mail) => $mail->templateKey === 'restaurant_submission_email_verification');
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
        $this->assertSame('48.8670000', $restaurant->latitude);
        $this->assertSame('2.3650000', $restaurant->longitude);
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

    public function test_every_verified_submitter_receives_an_account_activation_and_can_manage_without_blocking_a_claim(): void
    {
        $asset = MediaAsset::create(['original_path' => 'media/originals/test.jpg', 'mime' => 'image/jpeg', 'width' => 800, 'height' => 600, 'bytes' => 100, 'checksum' => str_repeat('z', 64), 'status' => 'ready']);
        $ingestor = Mockery::mock(MediaIngestor::class);
        $ingestor->shouldReceive('ingest')->once()->andReturn($asset);
        $this->app->instance(MediaIngestor::class, $ingestor);

        $this->post(route('restaurant-submissions.store'), $this->payload())->assertRedirect();
        $verification = Mail::queued(TemplateMailable::class)->first(fn (TemplateMailable $mail) => $mail->templateKey === 'restaurant_submission_email_verification');
        $this->get($verification->values['verification_url'])->assertOk();

        $confirmation = Mail::queued(TemplateMailable::class)->first(fn (TemplateMailable $mail) => $mail->templateKey === 'restaurant_submission_email_confirmed');
        $this->assertNotNull($confirmation);
        $this->assertArrayHasKey('activation_url', $confirmation->values);
        $activationUrl = explode('?', $confirmation->values['activation_url'])[0];
        $this->get($activationUrl)->assertOk()->assertSee('Activer mon espace');
        $this->post($activationUrl, ['password' => 'MotDePasseSolide!123', 'password_confirmation' => 'MotDePasseSolide!123'])->assertRedirect(route('account.dashboard'));

        $restaurant = Restaurant::firstOrFail();
        $user = User::where('email', 'contributeur@example.invalid')->firstOrFail();
        $this->assertSame('user', $user->role);
        $this->assertFalse($user->must_change_password);
        $this->assertAuthenticatedAs($user);
        $this->get($activationUrl)->assertOk()->assertSee('Votre espace est déjà activé.');
        $this->assertTrue($user->can('manage', $restaurant));
        $this->assertTrue($restaurant->isClaimable());
        $this->actingAs($user)->get(route('account.dashboard'))->assertOk()->assertSee($restaurant->name);
    }

    public function test_an_existing_active_account_is_reused_without_an_activation_token_or_account_mutation(): void
    {
        $user = User::factory()->create([
            'email' => 'Contributeur@Example.Invalid',
            'password' => Hash::make('MotDePasseActif!123'),
            'login_enabled' => true,
            'status' => 'active',
            'must_change_password' => false,
        ]);
        $before = $this->storedUserState($user->fresh(), ['password', 'email_verified_at', 'login_enabled', 'role', 'status', 'must_change_password']);
        $this->fakeSubmissionIngestor('active-existing-account');

        $this->post(route('restaurant-submissions.store'), $this->payload(['email' => 'contributeur@example.invalid']))->assertRedirect();
        $verification = Mail::queued(TemplateMailable::class)->first(fn (TemplateMailable $mail) => $mail->templateKey === 'restaurant_submission_email_verification');
        $this->get($verification->values['verification_url'])->assertOk();

        $submission = RestaurantSubmission::firstOrFail();
        $this->assertSame($user->id, $submission->user_id);
        $this->assertNull($submission->activation_token);
        $this->assertNull($submission->activation_expires_at);
        $this->assertSame($before, $this->storedUserState($user->fresh(), array_keys($before)));
        $this->assertDatabaseCount('users', 1);

        $confirmation = Mail::queued(TemplateMailable::class)->first(fn (TemplateMailable $mail) => $mail->templateKey === 'restaurant_submission_email_confirmed');
        $this->assertNotNull($confirmation);
        $this->assertArrayNotHasKey('activation_url', $confirmation->values);

        $submission->restaurant->update(['status' => 'published']);
        Mail::assertQueued(TemplateMailable::class, fn (TemplateMailable $mail) => $mail->templateKey === 'restaurant_submission_email_verification');
        Mail::assertQueued(TemplateMailable::class, fn (TemplateMailable $mail) => $mail->templateKey === 'restaurant_submission_email_confirmed');
        Mail::assertQueued(TemplateMailable::class, fn (TemplateMailable $mail) => $mail->templateKey === 'restaurant_published');
    }

    public function test_a_contribution_identity_is_reused_activated_and_keeps_its_review(): void
    {
        $identity = User::factory()->create([
            'email' => 'avis@example.invalid',
            'login_enabled' => false,
            'status' => 'active',
            'must_change_password' => false,
        ]);
        $reviewedRestaurant = Restaurant::create(['legacy_wp_id' => 887766, 'name' => 'Restaurant des avis', 'slug' => 'restaurant-des-avis', 'status' => 'published']);
        $review = RestaurantReview::create([
            'restaurant_id' => $reviewedRestaurant->id,
            'user_id' => $identity->id,
            'author_name' => 'Amina Martin',
            'author_email' => $identity->email,
            'rating' => 5,
            'content' => 'Très bon restaurant.',
            'status' => 'approved',
        ]);
        $this->fakeSubmissionIngestor('contribution-identity');

        $this->post(route('restaurant-submissions.store'), $this->payload(['email' => 'AVIS@example.invalid']))->assertRedirect();
        $verification = Mail::queued(TemplateMailable::class)->first(fn (TemplateMailable $mail) => $mail->templateKey === 'restaurant_submission_email_verification');
        $this->get($verification->values['verification_url'])->assertOk();

        $submission = RestaurantSubmission::firstOrFail();
        $this->assertSame($identity->id, $submission->user_id);
        $this->assertFalse($identity->fresh()->login_enabled);
        $this->assertFalse($identity->fresh()->must_change_password);
        $this->assertSame($identity->id, $review->fresh()->user_id);
        $this->assertDatabaseCount('users', 1);

        Password::spy();
        $this->from(route('login'))->post(route('login.store'), ['email' => $identity->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->post(route('password.email'), ['email' => $identity->email])->assertSessionHas('status');
        Password::shouldNotHaveReceived('sendResetLink');

        $confirmation = Mail::queued(TemplateMailable::class)->first(fn (TemplateMailable $mail) => $mail->templateKey === 'restaurant_submission_email_confirmed');
        $activationUrl = $confirmation->values['activation_url'];
        $this->get($activationUrl)->assertOk()->assertSee('Activer mon espace');
        $this->post($activationUrl, ['password' => 'MotDePasseSolide!123', 'password_confirmation' => 'MotDePasseSolide!123'])
            ->assertRedirect(route('account.dashboard'));

        $this->assertTrue($identity->fresh()->canLogIn());
        $this->assertFalse($identity->fresh()->must_change_password);
        $this->assertSame($identity->id, $review->fresh()->user_id);
        $this->post(route('logout'));
        $this->post(route('login.store'), ['email' => 'avis@example.invalid', 'password' => 'MotDePasseSolide!123'])
            ->assertRedirect(route('account.dashboard'));
        $this->post(route('logout'));
        $this->post(route('password.email'), ['email' => $identity->email])->assertSessionHas('status');
        Password::shouldHaveReceived('sendResetLink')->once()->with(['email' => $identity->email]);
    }

    public function test_an_existing_pending_activation_account_is_reused_without_a_duplicate(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'pending@example.invalid',
            'password' => Hash::make('MotDePasseInconnu!123'),
            'login_enabled' => true,
            'status' => 'active',
            'must_change_password' => true,
        ]);
        $password = $user->password;
        $this->fakeSubmissionIngestor('pending-activation-account');

        $this->post(route('restaurant-submissions.store'), $this->payload(['email' => 'PENDING@example.invalid']))->assertRedirect();
        $verification = Mail::queued(TemplateMailable::class)->first(fn (TemplateMailable $mail) => $mail->templateKey === 'restaurant_submission_email_verification');
        $this->get($verification->values['verification_url'])->assertOk();

        $submission = RestaurantSubmission::firstOrFail();
        $this->assertSame($user->id, $submission->user_id);
        $this->assertNotNull($submission->activation_token);
        $this->assertTrue($submission->activation_expires_at->isFuture());
        $this->assertSame($password, $user->fresh()->password);
        $this->assertTrue($user->fresh()->must_change_password);
        $this->assertDatabaseCount('users', 1);
        Mail::assertQueued(TemplateMailable::class, fn (TemplateMailable $mail) => $mail->templateKey === 'restaurant_submission_email_confirmed' && isset($mail->values['activation_url']));
    }

    public function test_a_legacy_must_change_password_account_keeps_its_existing_activation_state(): void
    {
        $legacy = User::factory()->unverified()->create([
            'email' => 'legacy@example.invalid',
            'legacy_wp_user_id' => 81234,
            'password' => Hash::make('LegacyTempPassword!123'),
            'login_enabled' => true,
            'status' => 'active',
            'must_change_password' => true,
        ]);
        $before = $legacy->only(['password', 'login_enabled', 'role', 'status', 'must_change_password', 'legacy_wp_user_id']);
        $this->fakeSubmissionIngestor('legacy-pending-password-change');

        $this->post(route('restaurant-submissions.store'), $this->payload(['email' => 'LEGACY@example.invalid']))->assertRedirect();
        $verification = Mail::queued(TemplateMailable::class)->first(fn (TemplateMailable $mail) => $mail->templateKey === 'restaurant_submission_email_verification');
        $this->get($verification->values['verification_url'])->assertOk();

        $submission = RestaurantSubmission::firstOrFail();
        $this->assertSame($legacy->id, $submission->user_id);
        $this->assertNotNull($submission->activation_token);
        $this->assertSame($before, $legacy->fresh()->only(array_keys($before)));
        $this->assertDatabaseCount('users', 1);
    }

    public function test_verified_email_makes_the_submission_available_for_admin_review_once(): void
    {
        Setting::create(['key' => 'contact_settings', 'group' => 'contact', 'value' => ['recipient' => 'team@example.invalid']]);
        $asset = MediaAsset::create(['original_path' => 'media/originals/test.jpg', 'mime' => 'image/jpeg', 'width' => 800, 'height' => 600, 'bytes' => 100, 'checksum' => str_repeat('d', 64), 'status' => 'ready']);
        $ingestor = Mockery::mock(MediaIngestor::class);
        $ingestor->shouldReceive('ingest')->once()->andReturn($asset);
        $this->app->instance(MediaIngestor::class, $ingestor);

        $this->post(route('restaurant-submissions.store'), $this->payload())->assertRedirect(route('restaurant-submissions.thanks'));
        $verification = Mail::queued(TemplateMailable::class)->first(fn (TemplateMailable $mail) => $mail->templateKey === 'restaurant_submission_email_verification');
        $this->assertNotNull($verification);
        $url = $verification->values['verification_url'];

        $this->get($url)->assertOk()->assertSee('Votre adresse e-mail est confirmée.');
        $submission = RestaurantSubmission::firstOrFail();
        $this->assertSame('pending_admin_review', $submission->status);
        $this->assertNotNull($submission->email_verified_at);
        $this->assertNotNull($submission->email_verification_token);
        $this->assertDatabaseHas('email_delivery_logs', ['template_key' => 'restaurant_submission_email_confirmed', 'recipient' => 'contributeur@example.invalid']);
        $this->assertDatabaseHas('email_delivery_logs', ['template_key' => 'restaurant_submission_admin_review', 'recipient' => 'team@example.invalid']);
        Mail::assertQueued(TemplateMailable::class, fn (TemplateMailable $mail) => $mail->templateKey === 'restaurant_submission_admin_review' && $mail->replyToAddress === 'contributeur@example.invalid');

        $this->get($url)->assertOk()->assertSee('Votre adresse était déjà confirmée.');
        $this->assertSame(1, Mail::queued(TemplateMailable::class)->filter(fn (TemplateMailable $mail) => $mail->templateKey === 'restaurant_submission_email_confirmed')->count());
        $this->assertSame(1, Mail::queued(TemplateMailable::class)->filter(fn (TemplateMailable $mail) => $mail->templateKey === 'restaurant_submission_admin_review')->count());
    }

    public function test_verified_owner_submission_becomes_an_owner_only_when_published_and_only_once(): void
    {
        $asset = MediaAsset::create(['original_path' => 'media/originals/owner.jpg', 'mime' => 'image/jpeg', 'width' => 800, 'height' => 600, 'bytes' => 100, 'checksum' => str_repeat('e', 64), 'status' => 'ready']);
        $ingestor = Mockery::mock(MediaIngestor::class);
        $ingestor->shouldReceive('ingest')->once()->andReturn($asset);
        $this->app->instance(MediaIngestor::class, $ingestor);

        $this->post(route('restaurant-submissions.store'), $this->payload([
            'submitter_role' => 'owner',
            'owner_full_name' => 'Amina Martin',
            'owner_company' => 'SARL Restaurant de test',
            'owner_siret' => '73282932000074',
            'owner_certified' => '1',
        ]))->assertRedirect(route('restaurant-submissions.thanks'));

        $verification = Mail::queued(TemplateMailable::class)->first(fn (TemplateMailable $mail) => $mail->templateKey === 'restaurant_submission_email_verification');
        $this->get($verification->values['verification_url'])->assertOk();
        $confirmation = Mail::queued(TemplateMailable::class)->first(fn (TemplateMailable $mail) => $mail->templateKey === 'restaurant_submission_email_confirmed');
        $activationUrl = explode('?', $confirmation->values['activation_url'])[0];
        $this->post($activationUrl, ['password' => 'MotDePasseSolide!123', 'password_confirmation' => 'MotDePasseSolide!123'])
            ->assertRedirect(route('account.dashboard'));

        $restaurant = Restaurant::firstOrFail();
        $submission = RestaurantSubmission::firstOrFail();
        $owner = User::where('email', 'contributeur@example.invalid')->firstOrFail();
        $claim = RestaurantClaim::firstOrFail();

        $this->assertSame('pending', $restaurant->status);
        $this->assertSame('pending_admin_review', $submission->status);
        $this->assertSame('pending_publication', $claim->status);
        $this->assertSame('new_submission', $claim->source);
        $this->assertSame($owner->id, $claim->user_id);
        $this->assertSame($owner->id, $submission->user_id);
        $this->assertFalse($owner->ownedRestaurants()->whereKey($restaurant->id)->exists());
        $this->assertTrue($owner->can('manage', $restaurant));

        $restaurant->update(['status' => 'published']);

        $this->assertSame('approved', $claim->fresh()->status);
        $this->assertTrue($owner->ownedRestaurants()->whereKey($restaurant->id)->exists());
        $this->assertTrue($owner->can('manage', $restaurant));
        $this->assertFalse($restaurant->fresh()->isClaimable());
        $this->actingAs($owner)->get(route('account.dashboard'))->assertOk()->assertSee($restaurant->name);
        $this->actingAs($owner)->get(route('owner.restaurants.edit', $restaurant))->assertOk();
        $this->assertDatabaseHas('restaurant_submissions', ['id' => $submission->id, 'restaurant_id' => $restaurant->id, 'user_id' => $owner->id, 'status' => 'published']);

        $restaurant->update(['status' => 'published']);
        $this->assertSame(1, RestaurantClaim::where('restaurant_id', $restaurant->id)->count());
        $this->assertSame(1, RestaurantClaim::where('restaurant_id', $restaurant->id)->where('user_id', $owner->id)->count());
    }

    public function test_published_non_owner_submission_never_creates_an_ownership_claim(): void
    {
        $restaurant = Restaurant::create(['name' => 'Déposant non gérant', 'slug' => 'deposant-non-gerant', 'status' => 'pending']);
        $depositor = User::factory()->create(['role' => 'user']);
        $submission = RestaurantSubmission::create([
            'restaurant_id' => $restaurant->id,
            'user_id' => $depositor->id,
            'submitter_email' => $depositor->email,
            'submitter_role' => 'customer',
            'status' => 'pending_admin_review',
            'email_verified_at' => now(),
            'submitted_at' => now(),
        ]);

        $restaurant->update(['status' => 'published']);

        $this->assertDatabaseCount('restaurant_claims', 0);
        $this->assertTrue($depositor->can('manage', $restaurant));
        $this->assertTrue($restaurant->fresh()->isClaimable());
        $this->assertDatabaseHas('restaurant_submissions', ['id' => $submission->id, 'user_id' => $depositor->id, 'status' => 'published']);
    }

    public function test_unverified_submission_cannot_be_published_and_invalid_or_expired_links_do_not_confirm_it(): void
    {
        $restaurant = Restaurant::create(['name' => 'En attente', 'slug' => 'en-attente', 'status' => 'pending']);
        $submission = RestaurantSubmission::create(['restaurant_id' => $restaurant->id, 'submitter_email' => 'contributeur@example.invalid', 'submitter_role' => 'customer', 'status' => 'pending_email_verification', 'submitted_at' => now(), 'email_verification_token' => hash('sha256', 'secret'), 'email_verification_expires_at' => now()->subMinute()]);

        try {
            $restaurant->update(['status' => 'published']);
            $this->fail('The restaurant must not be published before e-mail verification.');
        } catch (ValidationException) {
            $this->assertSame('pending', $restaurant->fresh()->status);
        }

        $invalid = URL::temporarySignedRoute('restaurant-submissions.verify', now()->addHour(), ['submission' => $submission, 'token' => 'incorrect']);
        $this->get($invalid)->assertNotFound();
        $expired = URL::temporarySignedRoute('restaurant-submissions.verify', now()->subMinute(), ['submission' => $submission, 'token' => 'secret']);
        $this->get($expired)->assertOk()->assertSee('Ce lien a expiré.');
        $this->assertSame('pending_email_verification', $submission->fresh()->status);
    }

    public function test_owner_submission_rejects_missing_certification_or_invalid_siret(): void
    {
        $this->actingAs(\App\Models\User::factory()->create())->from(route('restaurant-submissions.create'))->post(route('restaurant-submissions.store'), $this->payload(['submitter_role'=>'owner','owner_full_name'=>'Amina Martin','owner_company'=>'SARL Test','owner_siret'=>'123','owner_certified'=>null]))->assertSessionHasErrors(['owner_siret','owner_certified']);
    }

    public function test_address_endpoint_and_duplicate_endpoint_expose_only_the_safe_public_contract(): void
    {
        Restaurant::create(['legacy_wp_id' => 99, 'name' => 'Le Safran', 'slug' => 'le-safran', 'status' => 'published', 'address_line1' => '46 Boulevard du Temple', 'city_name' => 'Paris', 'latitude' => 48.866, 'longitude' => 2.364]);

        $this->getJson(route('restaurant-submissions.addresses', ['q' => 'Boulevard du Temple']))
            ->assertOk()
            ->assertJsonMissing(['city_code' => '75111']);

        $this->getJson(route('restaurant-submissions.duplicates', ['name' => 'Le Safran', 'address_line1' => '46 Boulevard du Temple', 'city_name' => 'Paris', 'latitude' => 48.866, 'longitude' => 2.364]))
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Le Safran')
            ->assertJsonPath('data.0.url', route('restaurants.show', 'le-safran'));
    }

    public function test_a_published_exact_duplicate_is_rejected_before_any_submission_side_effect(): void
    {
        $existing = $this->existingRestaurant(['name' => 'Restaurant de test', 'status' => 'published']);

        $this->from(route('restaurant-submissions.create'))->post(route('restaurant-submissions.store'), $this->payload())
            ->assertRedirect(route('restaurant-submissions.create'))
            ->assertSessionHasErrors('name')
            ->assertSessionHas('duplicate_restaurant.url', route('restaurants.show', $existing->slug))
            ->assertSessionHas('duplicate_restaurant.claim_url', route('claims.create', $existing));

        $this->assertDatabaseCount('restaurants', 1);
        $this->assertDatabaseCount('restaurant_submissions', 0);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('email_delivery_logs', 0);
        Mail::assertNothingQueued();
    }

    public function test_a_direct_post_cannot_bypass_the_server_side_exact_duplicate_check(): void
    {
        $this->existingRestaurant(['name' => 'Restaurant de test', 'status' => 'pending']);

        // No call to the informational GET endpoint precedes this manually built POST.
        $this->from(route('restaurant-submissions.create'))->post(route('restaurant-submissions.store'), $this->payload())
            ->assertRedirect(route('restaurant-submissions.create'))
            ->assertSessionHasErrors('name')
            ->assertSessionMissing('duplicate_restaurant.url');

        $this->assertDatabaseCount('restaurants', 1);
        $this->assertDatabaseCount('restaurant_submissions', 0);
        $this->assertDatabaseCount('email_delivery_logs', 0);
    }

    public function test_a_certain_duplicate_only_offers_claim_when_the_published_record_is_claimable(): void
    {
        $existing = $this->existingRestaurant(['name' => 'Restaurant de test', 'status' => 'published']);
        $owner = User::factory()->create();
        RestaurantClaim::create([
            'restaurant_id' => $existing->id, 'user_id' => $owner->id, 'email' => $owner->email,
            'status' => 'approved', 'submitted_at' => now(),
        ]);

        $this->from(route('restaurant-submissions.create'))->post(route('restaurant-submissions.store'), $this->payload())
            ->assertRedirect(route('restaurant-submissions.create'))
            ->assertSessionHasErrors('name')
            ->assertSessionHas('duplicate_restaurant.url', route('restaurants.show', $existing->slug))
            ->assertSessionMissing('duplicate_restaurant.claim_url');

        $this->assertDatabaseCount('restaurants', 1);
        $this->assertDatabaseCount('restaurant_submissions', 0);
        Mail::assertNothingQueued();
    }

    public function test_case_accent_hyphen_and_small_name_variants_at_the_same_address_are_certain_duplicates(): void
    {
        $this->existingRestaurant(['name' => 'Café du Monde', 'status' => 'published']);

        $this->from(route('restaurant-submissions.create'))->post(route('restaurant-submissions.store'), $this->payload(['name' => 'CAFE-DU monde']))
            ->assertRedirect(route('restaurant-submissions.create'))
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('restaurants', 1);
        $this->assertDatabaseCount('restaurant_submissions', 0);

        $this->from(route('restaurant-submissions.create'))->post(route('restaurant-submissions.store'), $this->payload(['name' => 'Cafe du Mnde']))
            ->assertRedirect(route('restaurant-submissions.create'))
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('restaurants', 1);
        $this->assertDatabaseCount('restaurant_submissions', 0);
    }

    public function test_same_name_at_a_different_address_is_allowed_without_a_duplicate_signal(): void
    {
        $this->existingRestaurant([
            'name' => 'Restaurant de test', 'address_line1' => '1 Rue des Lilas', 'postal_code' => '75011',
            'latitude' => 48.890, 'longitude' => 2.364,
        ]);
        $this->fakeSubmissionIngestor('same-name-different-address');

        $this->post(route('restaurant-submissions.store'), $this->payload())->assertRedirect(route('restaurant-submissions.thanks'));

        $this->assertDatabaseHas('restaurant_submissions', ['duplicate_signal' => null]);
    }

    public function test_same_address_with_a_clearly_different_establishment_stays_pending_and_is_signalled(): void
    {
        $this->existingRestaurant(['name' => 'Bowl du Centre', 'status' => 'published']);
        $this->fakeSubmissionIngestor('same-address-different-name');

        $this->post(route('restaurant-submissions.store'), $this->payload())->assertRedirect(route('restaurant-submissions.thanks'));

        $submission = RestaurantSubmission::firstOrFail();
        $this->assertSame('potential', $submission->duplicate_signal);
        $this->assertSame('same_address_different_name', $submission->duplicate_details[0]['reason']);
        $this->assertSame('pending_email_verification', $submission->status);
    }

    public function test_nearby_similar_name_with_a_different_address_stays_pending_and_is_signalled(): void
    {
        $this->existingRestaurant([
            'name' => 'Restaurant de test', 'address_line1' => '47 Boulevard du Temple',
            'latitude' => 48.8661, 'longitude' => 2.3641,
        ]);
        $this->fakeSubmissionIngestor('nearby-similar-name');

        $this->post(route('restaurant-submissions.store'), $this->payload())->assertRedirect(route('restaurant-submissions.thanks'));

        $submission = RestaurantSubmission::firstOrFail();
        $this->assertSame('pending_email_verification', $submission->status);
        $this->assertSame('potential', $submission->duplicate_signal);
        $this->assertSame('nearby_similar_name', $submission->duplicate_details[0]['reason']);
    }

    public function test_a_matching_phone_without_a_matching_address_never_blocks_a_submission(): void
    {
        $this->existingRestaurant([
            'name' => 'Autre restaurant', 'address_line1' => '1 Rue des Lilas', 'phone' => '+33 1 23 45 67 89',
            'latitude' => 48.890, 'longitude' => 2.364,
        ]);
        $this->fakeSubmissionIngestor('phone-without-address');

        $this->post(route('restaurant-submissions.store'), $this->payload(['phone' => '01 23 45 67 89']))->assertRedirect(route('restaurant-submissions.thanks'));

        $submission = RestaurantSubmission::firstOrFail();
        $this->assertSame('pending_email_verification', $submission->status);
        $this->assertSame('potential', $submission->duplicate_signal);
        $this->assertSame('same_phone_same_locality', $submission->duplicate_details[0]['reason']);
    }

    public function test_archived_and_trashed_matches_are_not_blocking_but_are_visible_to_moderation(): void
    {
        $this->existingRestaurant(['name' => 'Restaurant de test', 'status' => 'archived', 'slug' => 'restaurant-archive']);
        $trashed = $this->existingRestaurant(['name' => 'Restaurant de test', 'status' => 'published', 'slug' => 'restaurant-corbeille']);
        $trashed->delete();
        $this->fakeSubmissionIngestor('archived-and-trashed');

        $this->post(route('restaurant-submissions.store'), $this->payload())->assertRedirect(route('restaurant-submissions.thanks'));

        $submission = RestaurantSubmission::firstOrFail();
        $this->assertSame('potential', $submission->duplicate_signal);
        $this->assertCount(2, $submission->duplicate_details);
        $this->assertSame(['archived_exact_match', 'archived_exact_match'], array_column($submission->duplicate_details, 'reason'));
    }

    private function existingRestaurant(array $attributes = []): Restaurant
    {
        return Restaurant::create($attributes + [
            'legacy_wp_id' => random_int(1, 999999999), 'name' => 'Restaurant existant', 'slug' => 'restaurant-existant-'.str()->random(8), 'status' => 'published',
            'address_line1' => '46 Boulevard du Temple', 'postal_code' => '75011', 'city_name' => 'Paris', 'city_code' => '75111', 'country_code' => 'FR',
            'latitude' => 48.866, 'longitude' => 2.364,
        ]);
    }

    private function fakeSubmissionIngestor(string $checksumSeed): void
    {
        $asset = MediaAsset::create(['original_path' => 'media/originals/'.$checksumSeed.'.jpg', 'mime' => 'image/jpeg', 'width' => 800, 'height' => 600, 'bytes' => 100, 'checksum' => hash('sha256', $checksumSeed), 'status' => 'ready']);
        $ingestor = Mockery::mock(MediaIngestor::class);
        $ingestor->shouldReceive('ingest')->once()->andReturn($asset);
        $this->app->instance(MediaIngestor::class, $ingestor);
    }

    private function storedUserState(User $user, array $attributes): array
    {
        return collect($attributes)->mapWithKeys(fn (string $attribute): array => [$attribute => $user->getRawOriginal($attribute)])->all();
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
