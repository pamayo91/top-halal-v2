<?php

namespace Tests\Feature;

use App\Mail\TestEmail;
use App\Mail\TemplateMailable;
use App\Models\Restaurant;
use App\Models\RestaurantClaim;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\ClaimLifecycleNotification;
use App\Notifications\VerifyEmailNotification;
use App\Services\ClaimModeration;
use App\Services\SmtpConfigurationTestException;
use App\Services\SmtpConfigurationTester;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
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
        $user = User::factory()->create(['role' => 'restaurant_owner', 'must_change_password' => false]); $admin = User::factory()->create(['role' => 'admin']);
        $restaurant = Restaurant::create(['legacy_wp_id' => 999, 'name' => 'Test', 'slug' => 'test', 'status' => 'published']);
        $this->actingAs($user)->post('/restaurants/'.$restaurant->id.'/claim', ['certified' => '1']);
        $claim = RestaurantClaim::firstOrFail();
        Notification::assertSentTo($user, ClaimLifecycleNotification::class);
        $this->actingAs($admin);
        app(ClaimModeration::class)->approve($claim);
        Notification::assertSentToTimes($user, ClaimLifecycleNotification::class, 2);
    }

    public function test_test_mail_is_queued_without_transport_credentials(): void
    {
        Mail::fake();
        $this->artisan('mail:test', ['address' => 'test@example.test'])->assertExitCode(0);
        Mail::assertQueued(TestEmail::class);
    }

    public function test_smtp_configuration_test_is_sent_immediately_without_a_queue_job(): void
    {
        Setting::create(['key' => 'mail_settings', 'group' => 'email', 'value' => ['mailer' => 'smtp', 'host' => 'smtp.example.test', 'port' => 587, 'encryption' => 'tls', 'from_address' => 'noreply@example.test']]);
        Mail::fake();

        app(SmtpConfigurationTester::class)->send('recipient@example.test');

        Mail::assertSent(TestEmail::class, fn (TestEmail $mail) => true);
        Mail::assertNothingQueued();
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_smtp_configuration_test_logs_a_sanitised_error(): void
    {
        Setting::create(['key' => 'mail_settings', 'group' => 'email', 'value' => ['mailer' => 'smtp', 'host' => 'smtp.example.test']]);
        Log::spy();
        Mail::shouldReceive('mailer')->once()->with('smtp')->andThrow(new RuntimeException('SMTP authentication failed: password=not-for-display'));

        try {
            app(SmtpConfigurationTester::class)->send('recipient@example.test');
            $this->fail('The SMTP test should have failed.');
        } catch (SmtpConfigurationTestException $exception) {
            $this->assertStringContainsString('SMTP authentication failed', $exception->getMessage());
            $this->assertStringNotContainsString('not-for-display', $exception->getMessage());
        }

        Log::shouldHaveReceived('error')->once()->withArgs(function (string $message, array $context): bool {
            return $message === 'SMTP configuration test failed.'
                && str_contains($context['error'], 'password=[masqué]')
                && ! str_contains($context['error'], 'not-for-display');
        });
    }

    public function test_all_transactional_email_jobs_define_progressive_retry_settings(): void
    {
        foreach ([
            TemplateMailable::class,
            \App\Notifications\VerifyEmailNotification::class,
            \App\Notifications\QueuedResetPasswordNotification::class,
            \App\Notifications\PasswordChangedNotification::class,
            \App\Notifications\ClaimStatusNotification::class,
            \App\Notifications\LegacyAccountMigrationNotification::class,
        ] as $class) {
            $defaults = (new \ReflectionClass($class))->getDefaultProperties();

            $this->assertSame(4, $defaults['tries']);
            $this->assertSame(75, $defaults['timeout']);
            $this->assertSame([30, 120, 300], $defaults['backoff']);
        }
    }
}
