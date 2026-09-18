<?php

namespace Tests\Feature;

use App\Mail\TemplateMailable;
use App\Models\AccountEmailChange;
use App\Models\LegacyRestaurantAuthorship;
use App\Models\Restaurant;
use App\Models\RestaurantClaim;
use App\Models\RestaurantSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AccountEmailChangeTest extends TestCase
{
    use DatabaseMigrations;

    public function test_confirmation_changes_one_user_and_synchronizes_every_currently_represented_restaurant(): void
    {
        Mail::fake();
        $user = User::factory()->create(['name' => 'Amina Martin', 'email' => 'ancienne@example.test']);
        $owner = $this->restaurant('Chez Amina', $user->email);
        $historical = $this->restaurant('Le Souvenir', $user->email);
        $deposited = $this->restaurant('Le Dépôt', $user->email);
        $transferred = $this->restaurant('Ancien dépôt', $user->email);

        $claim = RestaurantClaim::create(['restaurant_id' => $owner->id, 'user_id' => $user->id, 'status' => 'approved', 'submitted_at' => now()]);
        $authorship = LegacyRestaurantAuthorship::create(['restaurant_id' => $historical->id, 'user_id' => $user->id, 'legacy_wp_id' => 90201, 'legacy_wp_user_id' => 80201, 'source_post_status' => 'publish']);
        $submission = RestaurantSubmission::create(['restaurant_id' => $deposited->id, 'user_id' => $user->id, 'submitter_email' => $user->email, 'submitter_role' => 'customer', 'status' => 'published', 'submitted_at' => now()]);
        RestaurantSubmission::create(['restaurant_id' => $transferred->id, 'user_id' => $user->id, 'submitter_email' => $user->email, 'submitter_role' => 'customer', 'status' => 'published', 'submitted_at' => now()]);
        RestaurantClaim::create(['restaurant_id' => $transferred->id, 'user_id' => User::factory()->create()->id, 'status' => 'approved', 'submitted_at' => now()]);

        $beforeUserCount = User::withTrashed()->count();
        $this->actingAs($user)->post(route('account.email-change.store'), [
            'email' => 'nouvelle@example.test',
            'current_password' => 'password',
        ])->assertRedirect(route('account.dashboard'));

        $this->assertSame('ancienne@example.test', $user->fresh()->email);
        $change = AccountEmailChange::firstOrFail();
        $this->assertSame('nouvelle@example.test', $change->new_email);
        $this->assertNotSame('nouvelle@example.test', $change->token_hash);
        $this->assertNull($change->used_at);
        Mail::assertQueued(TemplateMailable::class, fn (TemplateMailable $mail): bool => $mail->templateKey === 'account_email_change_verification' && $mail->hasTo('nouvelle@example.test'));

        /** @var TemplateMailable $verification */
        $verification = Mail::queued(TemplateMailable::class)->first(fn (TemplateMailable $mail): bool => $mail->templateKey === 'account_email_change_verification');
        $this->get($verification->values['verification_url'])->assertOk()->assertSee('Votre adresse e-mail est mise à jour.');

        $this->assertSame($beforeUserCount, User::withTrashed()->count());
        $this->assertSame('nouvelle@example.test', $user->fresh()->email);
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertSame('nouvelle@example.test', $owner->fresh()->contact_email);
        $this->assertSame('nouvelle@example.test', $historical->fresh()->contact_email);
        $this->assertSame('nouvelle@example.test', $deposited->fresh()->contact_email);
        $this->assertSame('ancienne@example.test', $transferred->fresh()->contact_email);
        $this->assertSame('ancienne@example.test', $submission->fresh()->submitter_email);
        $this->assertSame($user->id, $submission->fresh()->user_id);
        $this->assertSame($user->id, $claim->fresh()->user_id);
        $this->assertSame('approved', $claim->fresh()->status);
        $this->assertSame($user->id, $authorship->fresh()->user_id);
        $this->assertNotNull($change->fresh()->used_at);
        $this->assertDatabaseHas('email_delivery_logs', ['template_key' => 'account_email_changed', 'recipient' => 'ancienne@example.test']);
        $this->assertDatabaseMissing('email_delivery_logs', ['recipient' => $verification->values['verification_url']]);

        Mail::assertQueued(TemplateMailable::class, fn (TemplateMailable $mail): bool => $mail->templateKey === 'account_email_changed' && $mail->hasTo('ancienne@example.test'));
        $this->post(route('logout'));
        $this->post(route('login.store'), ['email' => 'nouvelle@example.test', 'password' => 'password'])->assertRedirect(route('account.dashboard'));
    }

    public function test_dashboard_has_separate_email_and_password_actions_and_restaurant_editor_has_no_email_field(): void
    {
        $user = User::factory()->create(['email' => 'compte@example.test']);
        $restaurant = $this->restaurant('Sans second e-mail', $user->email);
        RestaurantClaim::create(['restaurant_id' => $restaurant->id, 'user_id' => $user->id, 'status' => 'approved', 'submitted_at' => now()]);

        $this->actingAs($user)->get(route('account.dashboard'))
            ->assertOk()
            ->assertSeeText('Adresse e-mail')
            ->assertSeeText('compte@example.test')
            ->assertSeeText('Modifier mon adresse e-mail')
            ->assertSeeText('Mot de passe')
            ->assertSeeText('Changer le mot de passe');
        $this->get(route('owner.restaurants.edit', $restaurant))->assertOk()->assertDontSee('contact_email', false)->assertDontSee('E-mail professionnel');
    }

    public function test_new_email_must_be_unique_and_current_password_must_match(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'compte@example.test']);
        User::factory()->create(['email' => 'occupee@example.test']);

        $this->actingAs($user)->post(route('account.email-change.store'), [
            'email' => 'OCCUPEE@example.test',
            'current_password' => 'password',
        ])->assertSessionHasErrors('email');
        $this->assertDatabaseCount('account_email_changes', 0);

        $this->post(route('account.email-change.store'), [
            'email' => 'nouvelle@example.test',
            'current_password' => 'mauvais-mot-de-passe',
        ])->assertSessionHasErrors('current_password');
        $this->assertDatabaseCount('account_email_changes', 0);
        Mail::assertNothingQueued();
    }

    public function test_expired_and_consumed_tokens_never_change_the_current_email(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'ancienne@example.test']);
        $this->actingAs($user)->post(route('account.email-change.store'), ['email' => 'nouvelle@example.test', 'current_password' => 'password']);
        $change = AccountEmailChange::firstOrFail();
        /** @var TemplateMailable $mail */
        $mail = Mail::queued(TemplateMailable::class)->first();
        $change->update(['expires_at' => now()->subSecond()]);

        $this->get($mail->values['verification_url'])->assertOk()->assertSee('Ce lien a expiré.');
        $this->assertSame('ancienne@example.test', $user->fresh()->email);
        $this->assertNull($change->fresh()->used_at);

        $this->actingAs($user)->post(route('account.email-change.store'), ['email' => 'validee@example.test', 'current_password' => 'password']);
        /** @var TemplateMailable $validMail */
        $validMail = Mail::queued(TemplateMailable::class)->last(fn (TemplateMailable $queued): bool => $queued->templateKey === 'account_email_change_verification');
        $this->get($validMail->values['verification_url'])->assertOk();
        $this->get($validMail->values['verification_url'])->assertOk()->assertSee('Ce lien n’est plus utilisable.');
        $this->assertSame('validee@example.test', $user->fresh()->email);
    }

    private function restaurant(string $name, string $email): Restaurant
    {
        return Restaurant::create([
            'legacy_wp_id' => random_int(100000, 999999),
            'name' => $name,
            'slug' => str($name)->slug()->append('-'.str()->random(8)),
            'status' => 'published',
            'contact_email' => $email,
        ]);
    }
}
