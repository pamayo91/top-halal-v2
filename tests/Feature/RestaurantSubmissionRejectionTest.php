<?php

namespace Tests\Feature;

use App\Mail\TemplateMailable;
use App\Models\{Restaurant, RestaurantClaim, RestaurantSubmission, User};
use App\Services\RestaurantSubmissionModeration;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RestaurantSubmissionRejectionTest extends TestCase
{
    use DatabaseMigrations;

    public function test_pending_admin_review_submission_is_rejected_once_without_publishing_or_deleting_its_restaurant(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        [$restaurant, $submission] = $this->reviewableSubmission();

        $this->actingAs($admin);
        app(RestaurantSubmissionModeration::class)->reject($restaurant, 'Adresse insuffisamment vérifiable.');

        $submission->refresh();
        $restaurant->refresh();
        $this->assertSame('rejected', $submission->status);
        $this->assertSame('Adresse insuffisamment vérifiable.', $submission->admin_rejection_reason);
        $this->assertMatchesRegularExpression('/^TH-PROP-[A-Z0-9]{12}$/', $submission->rejection_reference);
        $this->assertNotNull($submission->rejected_at);
        $this->assertSame($admin->id, $submission->rejected_by);
        $this->assertSame('pending', $restaurant->status);
        $this->assertDatabaseHas('restaurants', ['id' => $restaurant->id, 'deleted_at' => null]);
        $this->assertDatabaseCount('restaurant_claims', 0);
        $this->assertDatabaseCount('email_delivery_logs', 1);

        Mail::assertQueued(TemplateMailable::class, function (TemplateMailable $mail) use ($submission): bool {
            return $mail->templateKey === 'restaurant_submission_rejected'
                && $mail->replyToAddress === null
                && $mail->values['rejection_reason'] === 'Motif communiqué : Adresse insuffisamment vérifiable.'
                && str_contains($mail->values['contact_url'], 'reference='.$submission->rejection_reference);
        });
    }

    public function test_rejection_email_contains_reason_no_reply_notice_and_contact_cta_which_prefills_safe_context(): void
    {
        Mail::fake();
        [$restaurant, $submission] = $this->reviewableSubmission();
        app(RestaurantSubmissionModeration::class)->reject($restaurant, 'Informations contradictoires.');

        /** @var TemplateMailable $mail */
        $mail = Mail::queued(TemplateMailable::class)->first(fn (TemplateMailable $mail) => $mail->templateKey === 'restaurant_submission_rejected');
        $html = $mail->render();
        $this->assertStringContainsString('Informations contradictoires.', $html);
        $this->assertStringContainsString('merci de ne pas y répondre', $html);
        $this->assertStringContainsString('Contester ce refus', $html);
        $this->assertNull($mail->replyToAddress);

        $this->get($mail->values['contact_url'])
            ->assertOk()
            ->assertSee('Contestation d’un refus de proposition')
            ->assertSee($submission->fresh()->rejection_reference);

        $this->post(route('contact.store'), [
            'name' => 'Amina', 'email' => 'amina@example.test',
            'subject' => 'Contestation d’un refus de proposition',
            'message' => 'Je pense que les informations sont exactes.',
            'submission_reference' => $submission->fresh()->rejection_reference,
            'website' => '',
        ])->assertRedirect(route('contact.create'));
        $this->assertDatabaseHas('contact_messages', [
            'subject' => 'Contestation d’un refus de proposition',
            'message' => "Référence proposition : {$submission->fresh()->rejection_reference}\n\nJe pense que les informations sont exactes.",
        ]);
    }

    public function test_a_second_refusal_or_a_publication_after_refusal_is_impossible_and_queues_no_second_email(): void
    {
        Mail::fake();
        [$restaurant, $submission] = $this->reviewableSubmission();
        app(RestaurantSubmissionModeration::class)->reject($restaurant);

        try {
            app(RestaurantSubmissionModeration::class)->reject($restaurant);
            $this->fail('A rejected submission must not be rejected twice.');
        } catch (ValidationException) {
            $this->assertSame(1, Mail::queued(TemplateMailable::class)->filter(fn (TemplateMailable $mail) => $mail->templateKey === 'restaurant_submission_rejected')->count());
        }

        try {
            $restaurant->fresh()->update(['status' => 'published']);
            $this->fail('A rejected submission must not publish its restaurant.');
        } catch (ValidationException) {
            $this->assertSame('pending', $restaurant->fresh()->status);
            $this->assertSame('rejected', $submission->fresh()->status);
        }
    }

    public function test_an_already_published_submission_cannot_be_refused(): void
    {
        Mail::fake();
        $restaurant = Restaurant::create(['name' => 'Déjà publié', 'slug' => 'deja-publie', 'status' => 'published']);
        RestaurantSubmission::create(['restaurant_id' => $restaurant->id, 'submitter_email' => 'deposant@example.test', 'submitter_role' => 'customer', 'status' => 'published', 'submitted_at' => now()]);

        $this->expectException(ValidationException::class);
        app(RestaurantSubmissionModeration::class)->reject($restaurant);
    }

    public function test_rejected_submission_cannot_be_activated_or_managed_by_its_depositor(): void
    {
        $depositor = User::factory()->create(['role' => 'user']);
        [$restaurant, $submission] = $this->reviewableSubmission($depositor);
        app(RestaurantSubmissionModeration::class)->reject($restaurant);
        $token = str()->random(64);
        $submission->update(['activation_token' => hash('sha256', $token), 'activation_expires_at' => now()->addDay()]);

        $this->get(route('restaurant-submissions.activate', ['submission' => $submission, 'token' => $token]))->assertNotFound();
        $this->assertFalse($depositor->can('manage', $restaurant));
        $this->actingAs($depositor)->get(route('account.dashboard'))->assertOk()->assertDontSee($restaurant->name);
        $this->actingAs($depositor)->get(route('owner.restaurants.edit', $restaurant))
            ->assertRedirect(route('owner.restaurants.management-unavailable', $restaurant));
    }

    public function test_refusal_only_revokes_the_rejected_proposal_and_leaves_an_active_users_account_claims_and_other_submission_intact(): void
    {
        Mail::fake();
        $user = User::factory()->create(['role' => 'user', 'login_enabled' => true, 'must_change_password' => false]);
        $before = $user->only(['login_enabled', 'password', 'role', 'status', 'must_change_password']);
        $owned = Restaurant::create(['name' => 'Restaurant déjà possédé', 'slug' => 'restaurant-deja-possede', 'status' => 'published']);
        $claim = RestaurantClaim::create(['restaurant_id' => $owned->id, 'user_id' => $user->id, 'status' => 'approved', 'submitted_at' => now()]);
        [$rejectedRestaurant, $rejected] = $this->reviewableSubmission($user);
        $rejected->update(['activation_token' => hash('sha256', 'rejected-token'), 'activation_expires_at' => now()->addDay()]);
        $validRestaurant = Restaurant::create(['name' => 'Proposition B valide', 'slug' => 'proposition-b-valide', 'status' => 'pending']);
        $valid = RestaurantSubmission::create(['restaurant_id' => $validRestaurant->id, 'user_id' => $user->id, 'submitter_email' => $user->email, 'submitter_role' => 'customer', 'status' => 'pending_admin_review', 'email_verified_at' => now(), 'submitted_at' => now()]);

        app(RestaurantSubmissionModeration::class)->reject($rejectedRestaurant);

        $this->assertSame($before, $user->fresh()->only(array_keys($before)));
        $this->assertSame('rejected', $rejected->fresh()->status);
        $this->assertNull($rejected->fresh()->activation_token);
        $this->assertSame('pending_admin_review', $valid->fresh()->status);
        $this->assertSame($user->id, $valid->fresh()->user_id);
        $this->assertSame('approved', $claim->fresh()->status);
        $this->assertTrue($user->can('manage', $owned));
        $this->assertTrue($user->can('manage', $validRestaurant));
        $this->assertFalse($user->can('manage', $rejectedRestaurant));

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('account.dashboard'));
        $this->get(route('account.dashboard'))
            ->assertOk()
            ->assertSee($owned->name)
            ->assertSee($validRestaurant->name)
            ->assertDontSee($rejectedRestaurant->name);
    }

    public function test_an_activation_link_for_another_valid_submission_still_works_after_a_refusal(): void
    {
        Mail::fake();
        $user = User::factory()->create(['login_enabled' => false, 'must_change_password' => true, 'role' => 'user', 'status' => 'active']);
        [$rejectedRestaurant, $rejected] = $this->reviewableSubmission($user);
        $rejectedToken = str()->random(64);
        $rejected->update(['activation_token' => hash('sha256', $rejectedToken), 'activation_expires_at' => now()->addDay()]);
        $validRestaurant = Restaurant::create(['name' => 'Proposition activable', 'slug' => 'proposition-activable', 'status' => 'pending']);
        $validToken = str()->random(64);
        $valid = RestaurantSubmission::create(['restaurant_id' => $validRestaurant->id, 'user_id' => $user->id, 'submitter_email' => $user->email, 'submitter_role' => 'customer', 'status' => 'pending_admin_review', 'email_verified_at' => now(), 'submitted_at' => now(), 'activation_token' => hash('sha256', $validToken), 'activation_expires_at' => now()->addDay()]);

        app(RestaurantSubmissionModeration::class)->reject($rejectedRestaurant);

        $this->get(route('restaurant-submissions.activate', ['submission' => $rejected, 'token' => $rejectedToken]))->assertNotFound();
        $this->get(route('restaurant-submissions.activate', ['submission' => $valid, 'token' => $validToken]))->assertOk();
        $this->post(route('restaurant-submissions.activate.store', ['submission' => $valid, 'token' => $validToken]), [
            'password' => 'MotDePasseSolide!123', 'password_confirmation' => 'MotDePasseSolide!123',
        ])->assertRedirect(route('account.dashboard'));

        $this->assertTrue($user->fresh()->login_enabled);
        $this->assertFalse($user->fresh()->must_change_password);
        $this->assertSame('rejected', $rejected->fresh()->status);
        $this->assertSame('pending_admin_review', $valid->fresh()->status);
    }

    /** @return array{Restaurant, RestaurantSubmission} */
    private function reviewableSubmission(?User $depositor = null): array
    {
        $restaurant = Restaurant::create(['name' => 'Proposition à examiner', 'slug' => 'proposition-a-examiner-'.str()->random(8), 'status' => 'pending']);
        $submission = RestaurantSubmission::create([
            'restaurant_id' => $restaurant->id,
            'user_id' => $depositor?->id,
            'submitter_email' => $depositor?->email ?? 'deposant@example.test',
            'submitter_role' => 'customer',
            'status' => 'pending_admin_review',
            'email_verified_at' => now(),
            'submitted_at' => now(),
        ]);

        return [$restaurant, $submission];
    }
}
