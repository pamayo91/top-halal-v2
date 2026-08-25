<?php

namespace Tests\Feature;

use App\Mail\TestEmail;
use App\Models\Restaurant;
use App\Models\RestaurantClaim;
use App\Models\User;
use App\Notifications\ClaimStatusNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmailDeliveryTest extends TestCase
{
    use DatabaseMigrations;

    public function test_registration_queues_a_signed_email_verification(): void
    {
        Notification::fake();
        $this->post('/register', ['name' => 'Élodie', 'email' => 'elodie@example.test', 'password' => 'password-long-123', 'password_confirmation' => 'password-long-123']);
        Notification::assertSentTo(User::whereEmail('elodie@example.test')->firstOrFail(), VerifyEmailNotification::class);
    }

    public function test_claim_lifecycle_queues_notifications(): void
    {
        Notification::fake();
        $user = User::factory()->create(); $admin = User::factory()->create(['role' => 'admin']);
        $restaurant = Restaurant::create(['legacy_wp_id' => 999, 'name' => 'Test', 'slug' => 'test', 'status' => 'published']);
        $this->actingAs($user)->post('/restaurants/'.$restaurant->id.'/claim', ['message' => 'Test']);
        $claim = RestaurantClaim::firstOrFail();
        Notification::assertSentTo($user, ClaimStatusNotification::class);
        $this->actingAs($admin)->patch('/admin/claims/'.$claim->id.'/approve');
        Notification::assertSentToTimes($user, ClaimStatusNotification::class, 2);
    }

    public function test_test_mail_is_queued_without_transport_credentials(): void
    {
        Mail::fake();
        $this->artisan('mail:test', ['address' => 'test@example.test'])->assertExitCode(0);
        Mail::assertQueued(TestEmail::class);
    }
}
