<?php

namespace Tests\Feature;

use App\Mail\TemplateMailable;
use App\Models\EmailDeliveryLog;
use App\Models\LegacyRestaurantAuthorship;
use App\Models\Restaurant;
use App\Models\RestaurantClaim;
use App\Models\RestaurantRemovalRequest;
use App\Models\RestaurantSubmission;
use App\Models\Setting;
use App\Models\User;
use App\Services\RestaurantRemovalModeration;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RestaurantRemovalRequestTest extends TestCase
{
    use DatabaseMigrations;

    public function test_authorized_depositor_can_request_removal_and_receives_the_acknowledgement(): void
    {
        Mail::fake();
        $restaurant = $this->restaurant();
        $depositor = User::factory()->create(['role' => 'user']);
        RestaurantSubmission::create([
            'restaurant_id' => $restaurant->id,
            'user_id' => $depositor->id,
            'submitter_email' => $depositor->email,
            'submitter_role' => 'customer',
            'status' => 'published',
            'submitted_at' => now(),
        ]);

        $this->actingAs($depositor)
            ->post(route('owner.restaurants.removal.store', $restaurant), ['reason' => 'closed'])
            ->assertRedirect(route('account.dashboard'));

        $removal = RestaurantRemovalRequest::firstOrFail();
        $this->assertSame('pending', $removal->status);
        $this->assertSame($depositor->id, $removal->user_id);
        $this->assertSame('published', $restaurant->fresh()->status);
        $this->assertDatabaseCount('email_delivery_logs', 1);
        Mail::assertQueued(TemplateMailable::class, fn (TemplateMailable $mail): bool => $mail->templateKey === 'restaurant_removal_request_received' && $mail->values['restaurant_name'] === $restaurant->name);

        $this->actingAs(User::factory()->create(['role' => 'admin']));
        app(RestaurantRemovalModeration::class)->approve($removal);

        $this->assertSame('approved', $removal->fresh()->status);
        $this->assertSame('archived', $restaurant->fresh()->status);
    }

    public function test_approved_owner_claim_including_new_submission_can_request_removal_and_be_approved(): void
    {
        Mail::fake();
        $restaurant = $this->restaurant();
        $owner = User::factory()->create(['role' => 'user']);
        RestaurantClaim::create([
            'restaurant_id' => $restaurant->id,
            'user_id' => $owner->id,
            'source' => 'new_submission',
            'status' => 'approved',
            'submitted_at' => now(),
        ]);

        $removal = $this->submit($owner, $restaurant);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        app(RestaurantRemovalModeration::class)->approve($removal);

        $this->assertSame('approved', $removal->fresh()->status);
        $this->assertSame('archived', $restaurant->fresh()->status);
        $this->assertDatabaseHas('restaurants', ['id' => $restaurant->id, 'deleted_at' => null]);
        Mail::assertQueued(TemplateMailable::class, fn (TemplateMailable $mail): bool => $mail->templateKey === 'restaurant_removal_request_approved');
    }

    public function test_historical_restaurateur_can_request_removal_and_be_approved(): void
    {
        Mail::fake();
        $restaurant = $this->restaurant();
        $historicalManager = User::factory()->create(['role' => 'user']);
        LegacyRestaurantAuthorship::create([
            'restaurant_id' => $restaurant->id,
            'user_id' => $historicalManager->id,
            'legacy_wp_id' => $restaurant->legacy_wp_id,
            'legacy_wp_user_id' => 9001,
            'source_post_status' => 'publish',
        ]);

        $removal = $this->submit($historicalManager, $restaurant);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        app(RestaurantRemovalModeration::class)->approve($removal);

        $this->assertSame('approved', $removal->fresh()->status);
        $this->assertSame('archived', $restaurant->fresh()->status);
    }

    public function test_user_without_current_management_right_and_guest_cannot_request_removal(): void
    {
        $restaurant = $this->restaurant();
        $standardUser = User::factory()->create(['role' => 'user']);

        $this->actingAs($standardUser)
            ->post(route('owner.restaurants.removal.store', $restaurant), ['reason' => 'closed'])
            ->assertRedirect(route('owner.restaurants.management-unavailable', $restaurant));
        Auth::logout();
        $this->post(route('owner.restaurants.removal.store', $restaurant), ['reason' => 'closed'])
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('restaurant_removal_requests', 0);
        $this->assertDatabaseCount('email_delivery_logs', 0);
    }

    public function test_former_depositor_who_lost_management_after_an_approved_claim_cannot_request_removal(): void
    {
        $restaurant = $this->restaurant();
        $formerDepositor = User::factory()->create(['role' => 'user']);
        $owner = User::factory()->create(['role' => 'user']);
        RestaurantSubmission::create([
            'restaurant_id' => $restaurant->id,
            'user_id' => $formerDepositor->id,
            'submitter_email' => $formerDepositor->email,
            'submitter_role' => 'customer',
            'status' => 'published',
            'submitted_at' => now(),
        ]);
        RestaurantClaim::create([
            'restaurant_id' => $restaurant->id,
            'user_id' => $owner->id,
            'status' => 'approved',
            'submitted_at' => now(),
        ]);

        $this->assertFalse($formerDepositor->can('manage', $restaurant));
        $this->actingAs($formerDepositor)
            ->post(route('owner.restaurants.removal.store', $restaurant), ['reason' => 'closed'])
            ->assertRedirect(route('owner.restaurants.management-unavailable', $restaurant));

        $this->assertDatabaseCount('restaurant_removal_requests', 0);
    }

    public function test_only_one_pending_request_is_allowed_and_does_not_queue_a_duplicate_email(): void
    {
        Mail::fake();
        $restaurant = $this->restaurant();
        $owner = $this->owner($restaurant);

        $this->submit($owner, $restaurant);
        $this->actingAs($owner)
            ->post(route('owner.restaurants.removal.store', $restaurant), ['reason' => 'duplicate'])
            ->assertStatus(409);

        $this->assertDatabaseCount('restaurant_removal_requests', 1);
        $this->assertDatabaseCount('email_delivery_logs', 1);
        $this->assertSame(1, Mail::queued(TemplateMailable::class)->filter(fn (TemplateMailable $mail): bool => $mail->templateKey === 'restaurant_removal_request_received')->count());
    }

    public function test_pending_removal_leaves_the_restaurant_public(): void
    {
        Mail::fake();
        $restaurant = $this->restaurant();
        $this->submit($this->owner($restaurant), $restaurant);

        $this->get(route('restaurants.show', $restaurant->slug))->assertOk();
        $this->assertSame('published', $restaurant->fresh()->status);
    }

    public function test_approval_removes_the_restaurant_from_public_pages_without_a_physical_delete_or_lost_history(): void
    {
        Mail::fake();
        $restaurant = $this->restaurant();
        $request = $this->submit($this->owner($restaurant), $restaurant);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        app(RestaurantRemovalModeration::class)->approve($request);

        $this->get(route('restaurants.show', $restaurant->slug))->assertNotFound();
        $this->assertDatabaseHas('restaurants', ['id' => $restaurant->id, 'status' => 'archived', 'deleted_at' => null]);
        $this->assertDatabaseHas('restaurant_removal_requests', ['id' => $request->id, 'status' => 'approved']);
        Mail::assertQueued(TemplateMailable::class, fn (TemplateMailable $mail): bool => $mail->templateKey === 'restaurant_removal_request_approved');
    }

    public function test_rejection_preserves_the_restaurant_and_queues_one_email_with_the_admin_note(): void
    {
        Mail::fake();
        $restaurant = $this->restaurant();
        $request = $this->submit($this->owner($restaurant), $restaurant);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        app(RestaurantRemovalModeration::class)->reject($request, 'La fiche doit rester visible pendant la vérification.');

        $this->assertSame('rejected', $request->fresh()->status);
        $this->assertSame('La fiche doit rester visible pendant la vérification.', $request->fresh()->admin_note);
        $this->assertSame('published', $restaurant->fresh()->status);
        $this->get(route('restaurants.show', $restaurant->slug))->assertOk();
        Mail::assertQueued(TemplateMailable::class, fn (TemplateMailable $mail): bool => $mail->templateKey === 'restaurant_removal_request_rejected' && $mail->values['removal_note'] === 'La fiche doit rester visible pendant la vérification.');
    }

    public function test_moderation_is_single_use_and_never_queues_a_second_result_email(): void
    {
        Mail::fake();
        $restaurant = $this->restaurant();
        $request = $this->submit($this->owner($restaurant), $restaurant);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $moderation = app(RestaurantRemovalModeration::class);
        $moderation->approve($request);

        try {
            $moderation->approve($request->fresh());
            $this->fail('La seconde validation devait être refusée.');
        } catch (ValidationException) {
        }

        $this->assertSame(1, EmailDeliveryLog::where('template_key', 'restaurant_removal_request_approved')->count());
        $this->assertSame(1, Mail::queued(TemplateMailable::class)->filter(fn (TemplateMailable $mail): bool => $mail->templateKey === 'restaurant_removal_request_approved')->count());
    }

    public function test_operational_alert_uses_the_same_transactional_queue_when_a_recipient_is_configured(): void
    {
        Mail::fake();
        Setting::create(['key' => 'contact_settings', 'group' => 'contact', 'value' => ['recipient' => 'operations@example.test']]);
        $restaurant = $this->restaurant();
        $this->submit($this->owner($restaurant), $restaurant);

        $this->assertDatabaseCount('email_delivery_logs', 2);
        Mail::assertQueued(TemplateMailable::class, fn (TemplateMailable $mail): bool => $mail->templateKey === 'restaurant_removal_request_admin_review' && $mail->replyToAddress !== null);
    }

    public function test_reason_is_required(): void
    {
        $restaurant = $this->restaurant();

        $this->actingAs($this->owner($restaurant))
            ->post(route('owner.restaurants.removal.store', $restaurant), [])
            ->assertSessionHasErrors('reason');

        $this->assertDatabaseCount('restaurant_removal_requests', 0);
    }

    private function submit(User $user, Restaurant $restaurant): RestaurantRemovalRequest
    {
        $this->actingAs($user)
            ->post(route('owner.restaurants.removal.store', $restaurant), ['reason' => 'closed'])
            ->assertRedirect(route('account.dashboard'));

        return RestaurantRemovalRequest::firstOrFail();
    }

    private function owner(Restaurant $restaurant): User
    {
        $owner = User::factory()->create(['role' => 'user']);
        RestaurantClaim::create([
            'restaurant_id' => $restaurant->id,
            'user_id' => $owner->id,
            'status' => 'approved',
            'submitted_at' => now(),
        ]);

        return $owner;
    }

    private function restaurant(): Restaurant
    {
        return Restaurant::create([
            'legacy_wp_id' => random_int(100000, 999999),
            'name' => 'Restaurant de test',
            'slug' => 'restaurant-suppression-'.str()->random(10),
            'status' => 'published',
        ]);
    }
}
