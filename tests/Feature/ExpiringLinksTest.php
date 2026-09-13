<?php

namespace Tests\Feature;

use App\Mail\TemplateMailable;
use App\Models\{Article, ContributionVerification, Restaurant, RestaurantClaim, RestaurantSubmission, User};
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\{Mail, Notification};
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ExpiringLinksTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void { parent::setUp(); Mail::fake(); Notification::fake(); }

    public function test_expired_submission_can_be_resent_once_and_old_token_is_useless(): void
    {
        $submission = $this->submission('pending_email_verification', 'old');
        $this->get(route('restaurant-submissions.verify', [$submission, 'old']))->assertOk()->assertSee('Renvoyer un nouveau lien');
        $this->post(route('restaurant-submissions.verify.resend', [$submission, 'old']))->assertRedirect();
        $this->assertNotSame(hash('sha256', 'old'), $submission->fresh()->email_verification_token);
        $this->get(route('restaurant-submissions.verify', [$submission, 'old']))->assertNotFound();
        Mail::assertQueued(TemplateMailable::class, fn ($mail) => $mail->templateKey === 'restaurant_submission_email_verification');
    }

    public function test_rejected_submission_and_claim_do_not_offer_a_resend(): void
    {
        $submission = $this->submission('rejected', 'old');
        $this->get(route('restaurant-submissions.verify', [$submission, 'old']))->assertOk()->assertDontSee('Renvoyer un nouveau lien');
        $claim = RestaurantClaim::create(['restaurant_id' => $submission->restaurant_id, 'email' => 'claim@example.test', 'full_name' => 'Amina', 'status' => 'rejected', 'email_verification_token' => hash('sha256', 'claim-old'), 'email_verification_expires_at' => now()->subMinute(), 'submitted_at' => now()]);
        $this->get(route('claims.verify', [$claim, 'claim-old']))->assertOk()->assertDontSee('Renvoyer un nouveau lien');
    }

    public function test_expired_activations_resend_only_for_active_unactivated_accounts_and_never_disabled_accounts(): void
    {
        $user = User::factory()->create(['status' => 'active', 'login_enabled' => false, 'must_change_password' => true]);
        $submission = $this->submission('pending_admin_review', 'activation-old', $user, 'activation_token');
        $this->get(route('restaurant-submissions.activate', [$submission, 'activation-old']))->assertOk()->assertSee('Renvoyer un nouveau lien');
        $this->post(route('restaurant-submissions.activate.resend', [$submission, 'activation-old']))->assertRedirect();
        $disabled = User::factory()->create(['status' => 'disabled', 'login_enabled' => false, 'must_change_password' => true]);
        $disabledSubmission = $this->submission('pending_admin_review', 'disabled-old', $disabled, 'activation_token');
        $this->get(route('restaurant-submissions.activate', [$disabledSubmission, 'disabled-old']))->assertOk()->assertDontSee('Renvoyer un nouveau lien');
        $this->post(route('restaurant-submissions.activate.resend', [$disabledSubmission, 'disabled-old']))->assertNotFound();
        $this->assertSame('disabled', $disabled->fresh()->status);
    }

    public function test_expired_first_claim_verification_and_activation_can_be_resent_but_rejected_or_consumed_claims_cannot(): void
    {
        $restaurant = Restaurant::create(['name' => 'Claimable', 'slug' => 'claimable', 'status' => 'published']);
        $claim = RestaurantClaim::create(['restaurant_id' => $restaurant->id, 'email' => 'claim@example.test', 'full_name' => 'Amina', 'status' => 'pending_email_verification', 'email_verification_token' => hash('sha256', 'claim-old'), 'email_verification_expires_at' => now()->subMinute(), 'submitted_at' => now()]);
        $this->get(route('claims.verify', [$claim, 'claim-old']))->assertOk()->assertSee('Renvoyer un nouveau lien');
        $this->post(route('claims.verify.resend', [$claim, 'claim-old']))->assertRedirect();
        $this->get(route('claims.verify', [$claim, 'claim-old']))->assertNotFound();
        $user = User::factory()->create(['status' => 'active', 'login_enabled' => false, 'must_change_password' => true]);
        $activation = RestaurantClaim::create(['restaurant_id' => $restaurant->id, 'user_id' => $user->id, 'email' => $user->email, 'full_name' => 'Amina', 'status' => 'approved', 'activation_token' => hash('sha256', 'activation-old'), 'activation_expires_at' => now()->subMinute(), 'submitted_at' => now()]);
        $this->get(route('claims.activate', [$activation, 'activation-old']))->assertOk()->assertSee('Renvoyer un nouveau lien');
        $this->post(route('claims.activate.resend', [$activation, 'activation-old']))->assertRedirect();
        $connectable = User::factory()->create(['status' => 'active', 'login_enabled' => true, 'must_change_password' => false]);
        $connectableClaim = RestaurantClaim::create(['restaurant_id' => $restaurant->id, 'user_id' => $connectable->id, 'email' => $connectable->email, 'full_name' => 'Amina', 'status' => 'approved', 'activation_token' => hash('sha256', 'connectable-old'), 'activation_expires_at' => now()->subMinute(), 'submitted_at' => now()]);
        $this->get(route('claims.activate', [$connectableClaim, 'connectable-old']))->assertOk()->assertDontSee('Renvoyer un nouveau lien');
        $this->post(route('claims.activate.resend', [$connectableClaim, 'connectable-old']))->assertNotFound();
        $consumedUser = User::factory()->create(['status' => 'active', 'login_enabled' => false, 'must_change_password' => true]);
        $consumed = RestaurantClaim::create(['restaurant_id' => $restaurant->id, 'user_id' => $consumedUser->id, 'email' => $consumedUser->email, 'full_name' => 'Amina', 'status' => 'approved', 'activation_token' => hash('sha256', 'used-activation'), 'activation_expires_at' => now(), 'submitted_at' => now()]);
        $consumedUser->update(['must_change_password' => false]);
        $this->get(route('claims.activate', [$consumed, 'used-activation']))->assertOk()->assertSee('Votre espace est déjà activé.')->assertDontSee('Renvoyer un nouveau lien');
    }

    public function test_resend_is_rate_limited_per_object_and_ip(): void
    {
        $submission = $this->submission('pending_email_verification', 'rate-old');
        RateLimiter::clear('expiring-link|'.$submission->id.'|127.0.0.1');
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $submission->update(['email_verification_token' => hash('sha256', 'rate-old'), 'email_verification_expires_at' => now()->subMinute()]);
            $this->post(route('restaurant-submissions.verify.resend', [$submission, 'rate-old']))->assertRedirect();
        }
        $submission->update(['email_verification_token' => hash('sha256', 'rate-old'), 'email_verification_expires_at' => now()->subMinute()]);
        $this->post(route('restaurant-submissions.verify.resend', [$submission, 'rate-old']))->assertTooManyRequests();
    }

    public function test_expired_review_comment_and_report_verifications_are_replaced_without_creating_contributions(): void
    {
        $restaurant = Restaurant::create(['name' => 'Publié', 'slug' => 'publie', 'status' => 'published']);
        $article = Article::create(['legacy_wp_id' => 987654, 'original_title' => 'Article', 'title' => 'Article', 'slug' => 'article-expire', 'legacy_url' => '/article-expire', 'status' => 'published']);
        foreach ([['review', 'restaurant', $restaurant->id], ['comment', 'article', $article->id], ['report', 'article', $article->id]] as [$type, $targetType, $targetId]) {
            $verification = ContributionVerification::create(['email' => $type.'@example.test', 'author_name' => 'Amina', 'contribution_type' => $type, 'target_type' => $targetType, 'target_id' => $targetId, 'payload' => $type === 'review' ? ['rating' => 5, 'content' => 'Très bien'] : ($type === 'comment' ? ['content' => 'Merci'] : ['message' => 'À corriger']), 'token_hash' => hash('sha256', $type.'-old'), 'expires_at' => now()->subMinute()]);
            $this->get(route('contributions.verify', [$verification, $type.'-old']))->assertOk()->assertSee('Renvoyer un nouveau lien');
            $this->post(route('contributions.verify.resend', [$verification, $type.'-old']))->assertRedirect();
            $this->get(route('contributions.verify', [$verification, $type.'-old']))->assertNotFound();
        }
        $this->assertDatabaseCount('restaurant_reviews', 0);
        $this->assertDatabaseCount('comments', 0);
        $this->assertDatabaseCount('editorial_content_reports', 0);
    }

    private function submission(string $status, string $token, ?User $user = null, string $column = 'email_verification_token'): RestaurantSubmission
    {
        $restaurant = Restaurant::create(['name' => 'En attente '.str()->random(6), 'slug' => 'en-attente-'.str()->random(8), 'status' => 'pending']);
        return RestaurantSubmission::create(['restaurant_id' => $restaurant->id, 'user_id' => $user?->id, 'submitter_email' => 'submitter-'.str()->random(6).'@example.test', 'submitter_role' => 'customer', 'status' => $status, 'submitted_at' => now(), $column => hash('sha256', $token), $column === 'activation_token' ? 'activation_expires_at' : 'email_verification_expires_at' => now()->subMinute()]);
    }
}
