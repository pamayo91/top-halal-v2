<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\RestaurantClaim;
use App\Models\User;
use App\Notifications\QueuedResetPasswordNotification;
use App\Services\ClaimModeration;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Notification;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AuthenticationAndClaimsTest extends TestCase
{
    use DatabaseMigrations;

    public function test_registration_login_logout_and_rate_limited_credentials_are_protected(): void
    {
        $this->post('/register', ['name' => 'Élodie', 'email' => 'elodie@example.test', 'password' => 'password-long-123', 'password_confirmation' => 'password-long-123'])
            ->assertRedirect('/account');
        $user = User::where('email', 'elodie@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('user', $user->role);

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $user->email, 'password' => 'password-long-123'])->assertRedirect('/account');

        $this->post('/logout');
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', ['email' => 'limited@example.test', 'password' => 'wrong-password'])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => 'limited@example.test', 'password' => 'wrong-password'])->assertStatus(429);
    }

    public function test_legacy_user_is_forced_to_change_password_and_cannot_bypass_it(): void
    {
        $user = User::factory()->create(['password' => Hash::make('legacy-temporary-password'), 'must_change_password' => true]);
        $this->post('/login', ['email' => $user->email, 'password' => 'legacy-temporary-password'])->assertRedirect('/change-password');
        $this->get('/account')->assertRedirect('/change-password');
        $this->get('/restaurants/'.$this->restaurant()->id.'/claim')->assertRedirect('/change-password');

        $this->put('/change-password', ['current_password' => 'legacy-temporary-password', 'password' => 'new-password-long-123', 'password_confirmation' => 'new-password-long-123'])
            ->assertRedirect('/account');
        $this->assertFalse($user->fresh()->must_change_password);
        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'legacy-temporary-password'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $user->email, 'password' => 'new-password-long-123'])->assertRedirect('/account');
    }

    public function test_forgot_and_reset_password_clear_legacy_password_requirement(): void
    {
        Notification::fake();
        $user = User::factory()->create(['must_change_password' => true]);
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status');
        Notification::assertSentTo($user, QueuedResetPasswordNotification::class);

        $token = Password::createToken($user);
        $this->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'changed-password-123', 'password_confirmation' => 'changed-password-123'])
            ->assertRedirect('/login');
        $this->assertFalse($user->fresh()->must_change_password);
        $this->post('/login', ['email' => $user->email, 'password' => 'changed-password-123'])->assertRedirect('/account');
    }

    public function test_claims_are_pending_then_approved_with_owner_access_only(): void
    {
        $restaurant = $this->restaurant();
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($owner)->post('/restaurants/'.$restaurant->id.'/claim', $this->claimPayload())->assertRedirect();
        $claim = RestaurantClaim::firstOrFail();
        $this->assertSame('pending', $claim->status);
        $this->actingAs($owner)->get('/account/restaurants/'.$restaurant->id.'/edit')->assertForbidden();
        $this->actingAs($other)->get('/claims/'.$claim->id)->assertForbidden();
        $this->actingAs($owner)->post('/restaurants/'.$restaurant->id.'/claim', $this->claimPayload())->assertSessionHasErrors('claim');

        $this->actingAs($admin);
        app(ClaimModeration::class)->approve($claim);
        $this->assertSame('approved', $claim->fresh()->status);
        $this->assertSame('restaurant_owner', $owner->fresh()->role);
        $this->actingAs($owner)->get('/account/restaurants/'.$restaurant->id.'/edit')->assertOk();
        $this->actingAs($other)->get('/account/restaurants/'.$restaurant->id.'/edit')->assertForbidden();
        $this->actingAs($owner)->put('/account/restaurants/'.$restaurant->id, ['name' => 'L’Étoile mise à jour'])->assertSessionHas('status');
        $this->assertSame('L’Étoile mise à jour', $restaurant->fresh()->name);
    }

    public function test_guest_claim_flow_explains_authentication_and_preserves_the_restaurant_destination(): void
    {
        $restaurant = $this->restaurant();
        $claimUrl = route('claims.create', $restaurant);

        $this->get($claimUrl)
            ->assertOk()
            ->assertSee('Revendiquer ce restaurant')
            ->assertSee($restaurant->name)
            ->assertSee(route('claims.login', $restaurant))
            ->assertSee(route('claims.register', $restaurant));

        $this->get(route('claims.login', $restaurant))
            ->assertRedirect(route('login'))
            ->assertSessionHas('url.intended', $claimUrl);

        $user = User::factory()->create(['password' => Hash::make('password-long-123')]);
        $this->post('/login', ['email' => $user->email, 'password' => 'password-long-123'])
            ->assertRedirect($claimUrl);
        $this->get($claimUrl)->assertOk()->assertSee('Nom / prénom');
    }

    public function test_registration_returns_to_the_claim_form_when_started_from_claim_authentication(): void
    {
        Notification::fake();
        $restaurant = $this->restaurant();
        $claimUrl = route('claims.create', $restaurant);

        $this->get(route('claims.register', $restaurant))
            ->assertRedirect(route('register'))
            ->assertSessionHas('url.intended', $claimUrl);
        $this->post('/register', ['name' => 'Amina Martin', 'email' => 'amina@example.test', 'password' => 'password-long-123', 'password_confirmation' => 'password-long-123'])
            ->assertRedirect($claimUrl);
        $this->get($claimUrl)->assertOk()->assertSee('Nom / prénom');
    }

    public function test_claim_form_is_direct_for_authenticated_users_and_claimability_is_rechecked(): void
    {
        $restaurant = $this->restaurant();
        $this->actingAs(User::factory()->create())->get(route('claims.create', $restaurant))
            ->assertOk()
            ->assertSee('Nom / prénom')
            ->assertDontSee('Pour revendiquer cet établissement');

        RestaurantClaim::create(['restaurant_id' => $restaurant->id, 'user_id' => User::factory()->create()->id, 'status' => 'pending', 'submitted_at' => now()]);
        $this->get(route('claims.create', $restaurant))->assertStatus(409);
        $this->get(route('claims.login', $restaurant))->assertStatus(409);
    }

    public function test_guest_cannot_bypass_claim_authentication_with_the_post_url(): void
    {
        $restaurant = $this->restaurant();
        $this->post(route('claims.store', $restaurant), $this->claimPayload())
            ->assertRedirect(route('login'));
    }

    public function test_claim_form_is_refused_if_the_restaurant_becomes_unclaimable_during_login(): void
    {
        $restaurant = $this->restaurant();
        $user = User::factory()->create(['password' => Hash::make('password-long-123')]);
        $claimUrl = route('claims.create', $restaurant);

        $this->get(route('claims.login', $restaurant))->assertRedirect(route('login'));
        RestaurantClaim::create(['restaurant_id' => $restaurant->id, 'user_id' => User::factory()->create()->id, 'status' => 'pending', 'submitted_at' => now()]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password-long-123'])->assertRedirect($claimUrl);
        $this->get($claimUrl)->assertStatus(409)->assertSee('Ce restaurant est déjà géré ou fait actuellement l’objet d’une demande de revendication.');
    }

    public function test_only_admin_can_moderate_and_rejection_does_not_promote_user(): void
    {
        $restaurant = $this->restaurant();
        $user = User::factory()->create();
        $claim = RestaurantClaim::create(['restaurant_id' => $restaurant->id, 'user_id' => $user->id, 'status' => 'pending', 'submitted_at' => now()]);
        $this->actingAs($user)->get('/admin')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        app(ClaimModeration::class)->reject($claim, 'Justificatif absent');
        $this->assertSame('rejected', $claim->fresh()->status);
        $this->assertSame('user', $user->fresh()->role);
    }

    private function restaurant(): Restaurant
    {
        return Restaurant::create(['legacy_wp_id' => random_int(1, 999999), 'name' => 'L’Étoile', 'slug' => 'l-etoile-'.str()->random(8), 'status' => 'published']);
    }
    private function claimPayload(): array { return ['full_name'=>'Amina Martin','company'=>'SARL Test','siret'=>'73282932000074','certified'=>'1','identity_document'=>UploadedFile::fake()->image('identity.jpg', 800, 600),'message'=>'Je représente ce restaurant.']; }
}
