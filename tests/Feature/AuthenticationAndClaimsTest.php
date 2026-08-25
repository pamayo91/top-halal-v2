<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\RestaurantClaim;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
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
        Notification::assertSentTo($user, ResetPassword::class);

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

        $this->actingAs($owner)->post('/restaurants/'.$restaurant->id.'/claim', ['message' => 'Je représente ce restaurant.'])->assertRedirect();
        $claim = RestaurantClaim::firstOrFail();
        $this->assertSame('pending', $claim->status);
        $this->actingAs($owner)->get('/account/restaurants/'.$restaurant->id.'/edit')->assertForbidden();
        $this->actingAs($other)->get('/claims/'.$claim->id)->assertForbidden();
        $this->actingAs($owner)->post('/restaurants/'.$restaurant->id.'/claim', ['message' => 'duplicate'])->assertSessionHasErrors('claim');

        $this->actingAs($admin)->patch('/admin/claims/'.$claim->id.'/approve')->assertRedirect('/admin/claims');
        $this->assertSame('approved', $claim->fresh()->status);
        $this->assertSame('restaurant_owner', $owner->fresh()->role);
        $this->actingAs($owner)->get('/account/restaurants/'.$restaurant->id.'/edit')->assertOk();
        $this->actingAs($other)->get('/account/restaurants/'.$restaurant->id.'/edit')->assertForbidden();
        $this->actingAs($owner)->put('/account/restaurants/'.$restaurant->id, ['name' => 'L’Étoile mise à jour'])->assertSessionHas('status');
        $this->assertSame('L’Étoile mise à jour', $restaurant->fresh()->name);
    }

    public function test_only_admin_can_moderate_and_rejection_does_not_promote_user(): void
    {
        $restaurant = $this->restaurant();
        $user = User::factory()->create();
        $claim = RestaurantClaim::create(['restaurant_id' => $restaurant->id, 'user_id' => $user->id, 'status' => 'pending', 'submitted_at' => now()]);
        $this->actingAs($user)->get('/admin/claims')->assertForbidden();
        $this->actingAs($user)->patch('/admin/claims/'.$claim->id.'/approve')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->patch('/admin/claims/'.$claim->id.'/reject', ['admin_note' => 'Justificatif absent'])->assertRedirect('/admin/claims');
        $this->assertSame('rejected', $claim->fresh()->status);
        $this->assertSame('user', $user->fresh()->role);
    }

    private function restaurant(): Restaurant
    {
        return Restaurant::create(['legacy_wp_id' => random_int(1, 999999), 'name' => 'L’Étoile', 'slug' => 'l-etoile-'.str()->random(8), 'status' => 'published']);
    }
}
