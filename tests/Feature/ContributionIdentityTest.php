<?php

namespace Tests\Feature;

use App\Mail\TemplateMailable;
use App\Models\{Article, Comment, ContributionVerification, Restaurant, RestaurantReview, Setting, User};
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\{Mail, URL};
use Tests\TestCase;

class ContributionIdentityTest extends TestCase
{
    use DatabaseMigrations;

    private Restaurant $restaurant;
    private Article $article;

    protected function setUp(): void
    {
        parent::setUp();
        $this->restaurant = Restaurant::create(['legacy_wp_id' => 7001, 'name' => 'Identité avis', 'slug' => 'identite-avis', 'status' => 'published']);
        $this->article = Article::create(['legacy_wp_id' => 7002, 'original_title' => 'Identité commentaire', 'title' => 'Identité commentaire', 'slug' => 'identite-commentaire', 'legacy_url' => '/identite-commentaire', 'status' => 'published']);
        Setting::create(['key' => 'contact_settings', 'group' => 'contact', 'value' => ['recipient' => 'operations@example.test']]);
        Mail::fake();
    }

    public function test_new_review_requires_email_verification_before_it_is_created_then_creates_an_identity_and_pending_review(): void
    {
        $this->post('/resto/identite-avis/avis', $this->reviewPayload())
            ->assertRedirect(route('restaurants.show', $this->restaurant->slug).'#avis');

        $this->assertDatabaseCount('restaurant_reviews', 0);
        $verification = ContributionVerification::sole();
        $this->assertSame('review', $verification->contribution_type);
        $this->assertNull($verification->used_at);
        Mail::assertQueued(TemplateMailable::class, fn (TemplateMailable $mail) => $mail->templateKey === 'contribution_email_verification' && $mail->values['user_name'] === 'Amina');
        Mail::assertNotQueued(TemplateMailable::class, fn (TemplateMailable $mail) => $mail->templateKey === 'restaurant_review_admin_review');

        $this->get($this->verificationUrl())
            ->assertOk()
            ->assertSee('Adresse confirmée')
            ->assertSee('Votre e-mail est confirmé.')
            ->assertDontSee('Votre identité est confirmée.')
            ->assertSee('Votre avis a bien été envoyé à l’équipe Top Halal. Il sera publié après validation.')
            ->assertSee('Retourner au restaurant')
            ->assertSee(route('restaurants.show', $this->restaurant->slug).'#avis');

        $this->get(route('restaurants.show', $this->restaurant->slug))
            ->assertOk()
            ->assertSee('Merci, votre avis a bien été confirmé. Il sera publié après validation par notre équipe.')
            ->assertDontSee('Vérifiez votre adresse e-mail pour confirmer votre avis.');

        $user = User::where('email', 'amina@example.test')->sole();
        $review = RestaurantReview::sole();
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertFalse($user->canLogIn());
        $this->assertSame('user', $user->role);
        $this->assertSame($user->id, $review->user_id);
        $this->assertSame('pending', $review->status);
        $this->assertNotNull($verification->fresh()->used_at);
        $this->assertNotNull($review->moderation_notification_log_id);
        $this->assertDatabaseHas('email_delivery_logs', ['id' => $review->moderation_notification_log_id, 'template_key' => 'restaurant_review_admin_review', 'recipient' => 'operations@example.test']);
        Mail::assertQueued(TemplateMailable::class, fn (TemplateMailable $mail) => $mail->templateKey === 'restaurant_review_admin_review'
            && $mail->hasTo('operations@example.test')
            && $mail->replyToAddress === 'amina@example.test'
            && $mail->values['admin_url'] === url('/admin/restaurant-reviews?tableFilters[status][value]=pending'));
    }

    public function test_trusted_identity_skips_a_new_email_but_an_absent_or_expired_proof_requires_one(): void
    {
        $this->post('/resto/identite-avis/avis', $this->reviewPayload())->assertRedirect();
        $this->get($this->verificationUrl())->assertOk();
        $user = User::where('email', 'amina@example.test')->sole();

        Mail::fake();
        $this->post('/resto/identite-avis/avis', $this->reviewPayload(['content' => 'Toujours excellent']))->assertRedirect();
        $this->assertSame(2, RestaurantReview::count());
        Mail::assertQueued(TemplateMailable::class, fn (TemplateMailable $mail) => $mail->templateKey === 'restaurant_review_admin_review');

        $this->app['session']->flush();
        $this->post('/resto/identite-avis/avis', $this->reviewPayload(['content' => 'Sans preuve']))->assertRedirect();
        $this->assertSame(2, RestaurantReview::count());
        $this->assertSame(2, ContributionVerification::count());

        $this->withSession(['contribution_identity_proofs' => [(string) $user->id => now()->subSecond()->timestamp]])
            ->post('/resto/identite-avis/avis', $this->reviewPayload(['content' => 'Preuve expirée']))->assertRedirect();
        $this->assertSame(3, ContributionVerification::count());
    }

    public function test_existing_email_is_reused_without_a_duplicate_only_after_verification_or_authenticated_session(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'amina@example.test', 'login_enabled' => false]);

        $this->post('/resto/identite-avis/avis', $this->reviewPayload())->assertRedirect();
        $this->assertDatabaseCount('users', 1);
        $this->get($this->verificationUrl())->assertOk();
        $this->assertDatabaseCount('users', 1);
        $this->assertSame($user->id, RestaurantReview::sole()->user_id);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());

        $restaurantUser = User::factory()->create(['role' => 'user', 'email' => 'owner@example.test']);
        Mail::fake();
        $this->actingAs($restaurantUser)->post('/resto/identite-avis/avis', [
            'name' => 'Nom affiché', 'rating' => 4, 'title' => 'Très bien', 'content' => 'Service impeccable.',
        ])->assertRedirect();
        $review = RestaurantReview::latest('id')->firstOrFail();
        $this->assertSame($restaurantUser->id, $review->user_id);
        $this->assertSame('owner@example.test', $review->author_email);
        Mail::assertNothingQueued();
    }

    public function test_email_comparison_is_case_insensitive_without_creating_a_second_identity(): void
    {
        $this->post('/resto/identite-avis/avis', $this->reviewPayload(['email' => 'Amina@Example.Test']))->assertRedirect();
        $this->get($this->verificationUrl())->assertOk();

        $user = User::where('email', 'amina@example.test')->sole();
        $this->post('/resto/identite-avis/avis', $this->reviewPayload(['email' => 'AMINA@EXAMPLE.TEST', 'content' => 'Même identité malgré la casse.']))->assertRedirect();

        $this->assertDatabaseCount('users', 1);
        $this->assertSame($user->id, RestaurantReview::latest('id')->value('user_id'));
    }

    public function test_contributor_identity_cannot_log_in_reset_a_password_or_receive_restaurant_rights(): void
    {
        $this->post('/resto/identite-avis/avis', $this->reviewPayload())->assertRedirect();
        $this->get($this->verificationUrl())->assertOk();
        $user = User::where('email', 'amina@example.test')->sole();

        $this->assertFalse($user->canLogIn());
        $this->assertSame('user', $user->role);
        $this->assertSame(0, $user->claims()->count());
        Mail::fake();
        $this->post('/login', ['email' => 'amina@example.test', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->post('/forgot-password', ['email' => 'amina@example.test'])->assertSessionHas('status');
        Mail::assertNothingQueued();
    }

    public function test_comments_use_the_same_verification_identity_flow(): void
    {
        $this->post('/identite-commentaire/commentaires', $this->commentPayload())->assertRedirect();
        $this->assertDatabaseCount('comments', 0);
        $this->assertSame('comment', ContributionVerification::sole()->contribution_type);

        $this->get($this->verificationUrl())->assertOk();
        $comment = Comment::sole();
        $this->assertSame('pending', $comment->status);
        $this->assertSame(User::where('email', 'amina@example.test')->value('id'), $comment->user_id);
        $this->assertNotNull($comment->moderation_notification_log_id);
        $this->assertDatabaseHas('email_delivery_logs', ['id' => $comment->moderation_notification_log_id, 'template_key' => 'editorial_comment_admin_review', 'recipient' => 'operations@example.test']);
        Mail::assertQueued(TemplateMailable::class, fn (TemplateMailable $mail) => $mail->templateKey === 'editorial_comment_admin_review'
            && $mail->hasTo('operations@example.test')
            && $mail->replyToAddress === 'amina@example.test'
            && $mail->values['admin_url'] === url('/admin/comments?tableFilters[status][value]=pending'));
    }

    public function test_authenticated_contributors_queue_one_operational_alert_when_their_review_or_comment_enters_pending(): void
    {
        $user = User::factory()->create(['email' => 'connecte@example.test']);

        $this->actingAs($user)->post('/resto/identite-avis/avis', [
            'name' => 'Amina', 'rating' => 4, 'content' => 'Très bon service.',
        ])->assertRedirect();
        $this->actingAs($user)->post('/identite-commentaire/commentaires', [
            'name' => 'Amina', 'content' => 'Merci pour ces informations.',
        ])->assertRedirect();

        $this->assertDatabaseCount('restaurant_reviews', 1);
        $this->assertDatabaseCount('comments', 1);
        $this->assertDatabaseCount('email_delivery_logs', 2);
        Mail::assertQueued(TemplateMailable::class, 2);
        Mail::assertQueued(TemplateMailable::class, fn (TemplateMailable $mail) => $mail->templateKey === 'restaurant_review_admin_review');
        Mail::assertQueued(TemplateMailable::class, fn (TemplateMailable $mail) => $mail->templateKey === 'editorial_comment_admin_review');
    }

    public function test_expired_and_used_tokens_never_create_a_second_contribution(): void
    {
        $expired = ContributionVerification::create([
            'email' => 'expired@example.test', 'author_name' => 'Expiré', 'contribution_type' => 'review', 'target_type' => 'restaurant', 'target_id' => $this->restaurant->id,
            'payload' => ['rating' => 5, 'title' => null, 'content' => 'Très bon'], 'token_hash' => hash('sha256', 'expired-token'), 'expires_at' => now()->subMinute(),
        ]);
        $expiredUrl = URL::temporarySignedRoute('contributions.verify', now()->subMinute(), ['verification' => $expired, 'token' => 'expired-token']);
        $this->get($expiredUrl)->assertOk()->assertSee('Ce lien a expiré.');
        $this->assertDatabaseCount('restaurant_reviews', 0);

        $this->post('/resto/identite-avis/avis', $this->reviewPayload())->assertRedirect();
        $url = $this->verificationUrl();
        $this->get($url)->assertOk();
        $this->get($url)->assertOk()->assertSee('Cet avis a déjà été confirmé.');
        $this->assertDatabaseCount('restaurant_reviews', 1);
        $this->assertDatabaseCount('email_delivery_logs', 2);
        Mail::assertQueued(TemplateMailable::class, 2);
    }

    public function test_urls_remain_rejected_for_reviews_and_comments(): void
    {
        $this->post('/resto/identite-avis/avis', $this->reviewPayload(['content' => 'https://example.test']))->assertSessionHasErrors('content');
        $this->post('/identite-commentaire/commentaires', $this->commentPayload(['content' => 'www.example.test']))->assertSessionHasErrors('content');
        $this->assertDatabaseCount('contribution_verifications', 0);
    }

    public function test_unvalidated_or_oversized_payloads_never_reach_the_temporary_verification_record(): void
    {
        $this->post('/resto/identite-avis/avis', $this->reviewPayload(['content' => str_repeat('a', 3001)]))->assertSessionHasErrors('content');
        $this->post('/identite-commentaire/commentaires', $this->commentPayload(['content' => str_repeat('a', 2001)]))->assertSessionHasErrors('content');
        $this->post('/resto/identite-avis/avis', $this->reviewPayload(['name' => str_repeat('a', 101)]))->assertSessionHasErrors('name');

        $this->assertDatabaseCount('contribution_verifications', 0);
        $this->assertDatabaseCount('restaurant_reviews', 0);
        $this->assertDatabaseCount('comments', 0);
    }

    private function verificationUrl(): string
    {
        $mail = Mail::queued(TemplateMailable::class)->first(fn (TemplateMailable $mail) => $mail->templateKey === 'contribution_email_verification');

        return $mail->values['verification_url'];
    }

    private function reviewPayload(array $overrides = []): array
    {
        return [...['name' => 'Amina', 'email' => 'amina@example.test', 'rating' => 5, 'title' => 'Excellent', 'content' => 'Très bon accueil.'], ...$overrides];
    }

    private function commentPayload(array $overrides = []): array
    {
        return [...['name' => 'Amina', 'email' => 'amina@example.test', 'content' => 'Merci pour cet article.'], ...$overrides];
    }
}
