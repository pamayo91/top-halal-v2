<?php

namespace Tests\Feature;

use App\Mail\TemplateMailable;
use App\Models\{Article, ContributionVerification, EditorialContentReport, Page, Restaurant, RestaurantClaim, RestaurantSubmission, Setting, User};
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\{Mail, URL};
use Tests\TestCase;

class ContentReportWorkflowTest extends TestCase
{
    use DatabaseMigrations;

    private Restaurant $restaurant;
    private Article $article;
    private Page $page;

    protected function setUp(): void
    {
        parent::setUp();
        $this->restaurant = Restaurant::create(['legacy_wp_id' => 8101, 'name' => 'Restaurant signalé', 'slug' => 'restaurant-signale', 'status' => 'published']);
        $this->article = Article::create(['legacy_wp_id' => 8102, 'original_title' => 'Article signalé', 'title' => 'Article signalé', 'slug' => 'article-signale', 'legacy_url' => '/article-signale', 'status' => 'published']);
        $this->page = Page::create(['legacy_wp_id' => 8103, 'original_title' => 'Page signalée', 'title' => 'Page signalée', 'slug' => 'page-signalee', 'legacy_url' => '/page-signalee', 'status' => 'published']);
        Setting::create(['key' => 'contact_settings', 'group' => 'contact', 'value' => ['recipient' => 'operations@example.test']]);
        Mail::fake();
    }

    public function test_new_visitor_must_verify_before_a_report_is_created_then_gets_one_verified_identity_and_a_new_report(): void
    {
        $this->post($this->restaurantUrl(), $this->payload())->assertRedirect()->assertSessionHas('content_report_verification_sent');
        $this->assertDatabaseCount('editorial_content_reports', 0);
        $verification = ContributionVerification::sole();
        $this->assertSame('report', $verification->contribution_type);
        Mail::assertQueued(TemplateMailable::class, fn (TemplateMailable $mail) => $mail->templateKey === 'contribution_email_verification');

        $this->get($this->verificationUrl())->assertOk()->assertSee('signalement');
        $user = User::where('email', 'amina@example.test')->sole();
        $report = EditorialContentReport::sole();
        $this->assertSame($user->id, $report->user_id);
        $this->assertSame('new', $report->status);
        $this->assertSame('restaurant', $report->content_type);
        $this->assertSame($this->restaurant->id, $report->content_id);
        $this->assertSame(route('restaurants.show', $this->restaurant->slug), $report->content_url);
        $this->assertFalse($report->is_authenticated);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertNotNull($verification->fresh()->used_at);
        Mail::assertQueued(TemplateMailable::class, fn (TemplateMailable $mail) => $mail->templateKey === 'content_report_admin_review' && $mail->hasTo('operations@example.test'));
        $this->assertDatabaseHas('email_delivery_logs', ['template_key' => 'content_report_admin_review', 'recipient' => 'operations@example.test']);
    }

    public function test_known_email_without_or_with_expired_proof_requires_a_fresh_verification_but_valid_proof_creates_directly(): void
    {
        $user = User::factory()->create(['email' => 'amina@example.test']);
        $this->post($this->restaurantUrl(), $this->payload())->assertRedirect();
        $this->assertDatabaseCount('editorial_content_reports', 0);
        $this->assertDatabaseCount('contribution_verifications', 1);

        $this->withSession(['contribution_identity_proofs' => [(string) $user->id => now()->addHour()->timestamp]])
            ->post($this->restaurantUrl(), $this->payload(['message' => 'Horaires à corriger.']))->assertRedirect()->assertSessionHas('content_report_submitted');
        $this->assertDatabaseCount('editorial_content_reports', 1);
        $this->assertSame($user->id, EditorialContentReport::sole()->user_id);

        $this->withSession(['contribution_identity_proofs' => [(string) $user->id => now()->subSecond()->timestamp]])
            ->post($this->restaurantUrl(), $this->payload(['message' => 'Preuve expirée.']))->assertRedirect();
        $this->assertDatabaseCount('contribution_verifications', 2);
    }

    public function test_authenticated_user_and_manager_keep_their_own_identity_and_never_verify_again(): void
    {
        $user = User::factory()->create(['email' => 'manager@example.test', 'name' => 'Gestionnaire']);
        RestaurantClaim::create(['restaurant_id' => $this->restaurant->id, 'user_id' => $user->id, 'status' => 'approved', 'submitted_at' => now()]);

        $this->actingAs($user)->post($this->restaurantUrl(), $this->payload(['email' => 'other@example.test']))->assertRedirect()->assertSessionHas('content_report_submitted');
        $report = EditorialContentReport::sole();
        $this->assertSame($user->id, $report->user_id);
        $this->assertSame('manager@example.test', $report->reporter_email);
        $this->assertTrue($report->is_authenticated);
        $this->assertDatabaseCount('contribution_verifications', 0);
        $this->assertDatabaseCount('users', 1);
        $this->actingAs($user)->get(route('restaurants.show', $this->restaurant->slug))->assertOk()->assertSee('Modifier les informations de cette fiche');
    }

    public function test_article_and_page_reports_retain_their_distinct_context_and_no_content_is_changed(): void
    {
        $user = User::factory()->create();
        $beforeRestaurant = $this->restaurant->fresh()->toArray();
        $beforeArticle = $this->article->fresh()->toArray();
        $beforePage = $this->page->fresh()->toArray();
        $this->actingAs($user)->post('/article-signale/signaler-une-erreur', $this->payload())->assertRedirect();
        $this->actingAs($user)->post('/page-signalee/signaler-une-erreur', $this->payload(['message' => 'Texte à actualiser.']))->assertRedirect();
        $this->assertDatabaseHas('editorial_content_reports', ['content_type' => 'article', 'content_id' => $this->article->id, 'status' => 'new']);
        $this->assertDatabaseHas('editorial_content_reports', ['content_type' => 'page', 'content_id' => $this->page->id, 'status' => 'new']);
        $this->assertSame($beforeRestaurant, $this->restaurant->fresh()->toArray());
        $this->assertSame($beforeArticle, $this->article->fresh()->toArray());
        $this->assertSame($beforePage, $this->page->fresh()->toArray());
    }

    public function test_expired_or_used_token_cannot_create_a_report_and_honeypot_and_size_are_preserved(): void
    {
        $expired = ContributionVerification::create(['email' => 'expired@example.test', 'author_name' => 'Expiré', 'contribution_type' => 'report', 'target_type' => 'restaurant', 'target_id' => $this->restaurant->id, 'payload' => ['message' => 'Trop tard'], 'token_hash' => hash('sha256', 'expired'), 'expires_at' => now()->subMinute()]);
        $url = URL::temporarySignedRoute('contributions.verify', now()->subMinute(), ['verification' => $expired, 'token' => 'expired']);
        $this->get($url)->assertForbidden();
        $this->post($this->restaurantUrl(), $this->payload(['website' => 'bot']))->assertSessionHasErrors('website');
        $this->post($this->restaurantUrl(), $this->payload(['message' => str_repeat('a', 2001)]))->assertSessionHasErrors('message');
        $this->assertDatabaseCount('editorial_content_reports', 0);
    }

    public function test_back_office_status_transitions_are_limited_to_the_simple_workflow(): void
    {
        $report = EditorialContentReport::create(['content_type' => 'restaurant', 'content_id' => $this->restaurant->id, 'content_title' => $this->restaurant->name, 'content_url' => $this->restaurantUrl(), 'message' => 'À corriger', 'ip_hash' => str_repeat('a', 64), 'status' => 'new']);
        $report->update(['status' => 'in_progress', 'status_changed_at' => now()]);
        $this->assertSame('in_progress', $report->fresh()->status);
        $report->update(['status' => 'resolved', 'status_changed_at' => now()]);
        $this->assertSame('resolved', $report->fresh()->status);
        $dismissed = EditorialContentReport::create(['content_type' => 'restaurant', 'content_id' => $this->restaurant->id, 'content_title' => $this->restaurant->name, 'content_url' => $this->restaurantUrl(), 'message' => 'À classer', 'ip_hash' => str_repeat('b', 64), 'status' => 'new']);
        $dismissed->update(['status' => 'in_progress', 'status_changed_at' => now()]);
        $dismissed->update(['status' => 'dismissed', 'status_changed_at' => now()]);
        $this->assertSame('dismissed', $dismissed->fresh()->status);
    }

    private function restaurantUrl(): string { return '/resto/restaurant-signale/signaler-une-erreur'; }
    private function verificationUrl(): string { return Mail::queued(TemplateMailable::class)->first(fn (TemplateMailable $mail) => $mail->templateKey === 'contribution_email_verification')->values['verification_url']; }
    private function payload(array $overrides = []): array { return [...['email' => 'amina@example.test', 'message' => 'Les horaires affichés ne sont plus exacts.'], ...$overrides]; }
}
