<?php

namespace Tests\Feature;

use App\Exceptions\RestaurantReviewOwnershipException;
use App\Mail\TemplateMailable;
use App\Models\{Article, Comment, ContributionVerification, LegacyRestaurantAuthorship, Restaurant, RestaurantClaim, RestaurantReview, RestaurantSubmission, User};
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RestaurantReviewOwnershipTest extends TestCase
{
    use DatabaseMigrations;

    private Restaurant $restaurant;
    private Restaurant $otherRestaurant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = $this->restaurant('restaurant-proprietaire');
        $this->otherRestaurant = $this->restaurant('autre-restaurant');
    }

    public function test_owner_with_an_approved_claim_cannot_submit_a_review_even_by_direct_post(): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        RestaurantClaim::create(['restaurant_id' => $this->restaurant->id, 'user_id' => $owner->id, 'status' => 'approved', 'submitted_at' => now()]);

        $this->assertReviewIsRefused($owner);
    }

    public function test_owner_of_a_published_new_submission_cannot_submit_a_review(): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        RestaurantSubmission::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $owner->id,
            'submitter_email' => $owner->email,
            'submitter_role' => 'owner',
            'owner_full_name' => 'Amina Martin',
            'status' => 'published',
            'submitted_at' => now(),
        ]);

        $this->assertReviewIsRefused($owner);
    }

    public function test_historical_restaurant_manager_cannot_submit_a_review(): void
    {
        $manager = User::factory()->create(['role' => 'user']);
        LegacyRestaurantAuthorship::create(['restaurant_id' => $this->restaurant->id, 'user_id' => $manager->id, 'legacy_wp_id' => 7001, 'legacy_wp_user_id' => 401, 'source_post_status' => 'publish']);

        $this->assertReviewIsRefused($manager);
    }

    public function test_non_manager_depositor_with_an_active_management_right_cannot_review_their_own_restaurant(): void
    {
        $depositor = User::factory()->create(['role' => 'user']);
        RestaurantSubmission::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $depositor->id,
            'submitter_email' => $depositor->email,
            'submitter_role' => 'customer',
            'status' => 'published',
            'submitted_at' => now(),
        ]);

        $this->assertFalse($depositor->can('isRestaurantManager', $this->restaurant));
        $this->assertTrue($depositor->can('manage', $this->restaurant));
        $this->assertFalse($depositor->can('mayReviewRestaurant', $this->restaurant));
        $this->assertReviewIsRefused($depositor);
    }

    public function test_non_manager_depositor_can_review_a_restaurant_they_do_not_manage(): void
    {
        $depositor = User::factory()->create(['role' => 'user']);
        RestaurantSubmission::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $depositor->id,
            'submitter_email' => $depositor->email,
            'submitter_role' => 'customer',
            'status' => 'published',
            'submitted_at' => now(),
        ]);

        $this->assertTrue($depositor->can('manage', $this->restaurant));
        $this->assertTrue($depositor->can('mayReviewRestaurant', $this->otherRestaurant));
        $this->actingAs($depositor)->post(route('restaurants.reviews.store', $this->otherRestaurant->slug), $this->reviewPayload())->assertRedirect();
        $this->assertDatabaseHas('restaurant_reviews', ['restaurant_id' => $this->otherRestaurant->id, 'user_id' => $depositor->id, 'status' => 'pending']);
    }

    public function test_standard_user_can_submit_a_review(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->post($this->reviewUrl(), $this->reviewPayload())->assertRedirect();
        $this->assertDatabaseHas('restaurant_reviews', ['restaurant_id' => $this->restaurant->id, 'user_id' => $user->id, 'status' => 'pending']);
    }

    public function test_manager_of_another_restaurant_can_submit_a_review(): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        RestaurantClaim::create(['restaurant_id' => $this->otherRestaurant->id, 'user_id' => $owner->id, 'status' => 'approved', 'submitted_at' => now()]);

        $this->assertFalse($owner->can('isRestaurantManager', $this->restaurant));
        $this->actingAs($owner)->post($this->reviewUrl(), $this->reviewPayload())->assertRedirect();
        $this->assertDatabaseHas('restaurant_reviews', ['restaurant_id' => $this->restaurant->id, 'user_id' => $owner->id, 'status' => 'pending']);
    }

    public function test_guest_review_flow_still_requires_email_verification_before_review_creation(): void
    {
        Mail::fake();

        $this->post($this->reviewUrl(), $this->reviewPayload(['email' => 'visiteur@example.test']))->assertRedirect();

        $this->assertDatabaseCount('restaurant_reviews', 0);
        $this->assertDatabaseHas('contribution_verifications', ['email' => 'visiteur@example.test', 'contribution_type' => 'review']);
    }

    public function test_verification_flow_rechecks_manager_status_before_creating_a_review(): void
    {
        Mail::fake();
        $owner = User::factory()->create(['role' => 'user', 'email' => 'gerant@example.test']);
        RestaurantClaim::create(['restaurant_id' => $this->restaurant->id, 'user_id' => $owner->id, 'status' => 'approved', 'submitted_at' => now()]);

        $this->post($this->reviewUrl(), $this->reviewPayload(['email' => $owner->email]))->assertRedirect();
        $mail = Mail::queued(TemplateMailable::class)->first(fn (TemplateMailable $mail): bool => $mail->templateKey === 'contribution_email_verification');

        $this->get($mail->values['verification_url'])
            ->assertOk()
            ->assertSee('Votre avis ne peut pas être publié.')
            ->assertSee(RestaurantReviewOwnershipException::MESSAGE);
        $this->assertDatabaseCount('restaurant_reviews', 0);
        $this->assertNotNull(ContributionVerification::sole()->fresh()->used_at);
    }

    public function test_editorial_comments_remain_available_to_a_restaurant_manager(): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        RestaurantClaim::create(['restaurant_id' => $this->restaurant->id, 'user_id' => $owner->id, 'status' => 'approved', 'submitted_at' => now()]);
        $article = Article::create(['legacy_wp_id' => 7010, 'original_title' => 'Article', 'title' => 'Article', 'slug' => 'article-avis-proprietaire', 'legacy_url' => '/article-avis-proprietaire', 'status' => 'published']);

        $this->actingAs($owner)->post(route('editorial.comments.store', $article->slug), ['name' => 'Gérant', 'content' => 'Un commentaire éditorial reste possible.'])->assertRedirect();

        $this->assertDatabaseHas('comments', ['article_id' => $article->id, 'user_id' => $owner->id, 'status' => 'pending']);
        $this->assertSame(0, RestaurantReview::count());
        $this->assertSame(1, Comment::count());
    }

    private function assertReviewIsRefused(User $user): void
    {
        $this->assertFalse($user->can('mayReviewRestaurant', $this->restaurant));
        $this->actingAs($user)->get(route('restaurants.show', $this->restaurant->slug))
            ->assertOk()
            ->assertSee(RestaurantReviewOwnershipException::MESSAGE)
            ->assertDontSee('Donner mon avis');

        $this->actingAs($user)->post($this->reviewUrl(), $this->reviewPayload())
            ->assertRedirect(route('restaurants.show', $this->restaurant->slug))
            ->assertSessionHas('review_ownership_forbidden', true);

        $this->assertDatabaseCount('restaurant_reviews', 0);
        $this->assertDatabaseCount('contribution_verifications', 0);
        $this->assertDatabaseCount('email_delivery_logs', 0);
    }

    private function restaurant(string $slug): Restaurant
    {
        return Restaurant::create(['legacy_wp_id' => random_int(10000, 99999), 'name' => 'Restaurant '.$slug, 'slug' => $slug, 'status' => 'published']);
    }

    private function reviewUrl(): string
    {
        return route('restaurants.reviews.store', $this->restaurant->slug);
    }

    private function reviewPayload(array $overrides = []): array
    {
        return [...['name' => 'Amina', 'rating' => 5, 'title' => 'Très bien', 'content' => 'Très bon accueil.'], ...$overrides];
    }
}
