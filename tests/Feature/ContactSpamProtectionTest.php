<?php

namespace Tests\Feature;

use App\Mail\TemplateMailable;
use App\Models\Setting;
use App\Services\ContactSpamProtection;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactSpamProtectionTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Setting::create(['key' => 'contact_settings', 'group' => 'contact', 'value' => ['recipient' => 'operations@example.test', 'send_confirmation' => true]]);
    }

    public function test_a_human_submission_creates_one_message_and_two_mail_jobs(): void
    {
        $this->postContact()->assertRedirect();
        $this->assertDatabaseCount('contact_messages', 1);
        $this->assertDatabaseCount('email_delivery_logs', 2);
        Mail::assertQueued(TemplateMailable::class, 2);
    }

    public function test_honeypot_and_immediate_time_trap_are_silent_and_have_no_business_effect(): void
    {
        $this->postContact(['website' => 'bot'])->assertRedirect(route('contact.create'));
        $this->assertDatabaseCount('contact_messages', 0);
        $this->assertDatabaseCount('email_delivery_logs', 0);
        Mail::assertNothingQueued();

        $this->post('/contact', $this->payload() + ['form_token' => app(ContactSpamProtection::class)->issueFormToken()])->assertRedirect(route('contact.create'));
        $this->assertDatabaseCount('contact_messages', 0);
        $this->assertDatabaseCount('email_delivery_logs', 0);
        Mail::assertNothingQueued();
    }

    public function test_same_content_is_deduplicated_even_when_the_ip_changes(): void
    {
        $this->postContact();
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.22'])->postContact();
        $this->assertDatabaseCount('contact_messages', 1);
        $this->assertDatabaseCount('email_delivery_logs', 2);
        Mail::assertQueued(TemplateMailable::class, 2);
    }

    public function test_ip_limit_blocks_without_creating_messages_or_mail_jobs(): void
    {
        for ($i = 0; $i < 5; $i++) { $this->postContact(['subject' => 'IP '.$i]); }
        $this->postContact(['subject' => 'IP blocked'])->assertSessionHasErrors('message');
        $this->assertDatabaseCount('contact_messages', 5);
        $this->assertDatabaseCount('email_delivery_logs', 10);

    }

    public function test_email_limit_applies_across_distinct_ips(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.'.($i + 1)])->postContact(['subject' => 'Email '.$i]);
        }
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.99'])->postContact(['subject' => 'Email blocked'])->assertSessionHasErrors('message');
        $this->assertDatabaseCount('contact_messages', 5);
        $this->assertDatabaseCount('email_delivery_logs', 10);
    }

    public function test_untrusted_forwarded_headers_do_not_change_the_ip_rate_limit_key(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['HTTP_X_FORWARDED_FOR' => '203.0.113.'.($i + 1)])->postContact(['subject' => 'Header '.$i]);
        }
        $this->withServerVariables(['HTTP_X_FORWARDED_FOR' => '203.0.113.99'])->postContact(['subject' => 'Header blocked'])->assertSessionHasErrors('message');
        $this->assertDatabaseCount('contact_messages', 5);
    }

    public function test_database_unique_fingerprint_is_the_concurrency_backstop(): void
    {
        $this->postContact();
        $this->postContact()->assertRedirect(route('contact.create'));
        $this->assertDatabaseCount('contact_messages', 1);
        Mail::assertQueued(TemplateMailable::class, 2);
    }

    private function postContact(array $overrides = [])
    {
        $token = app(ContactSpamProtection::class)->issueFormToken();
        Carbon::setTestNow(now()->addSeconds(3));
        try { return $this->post('/contact', $overrides + $this->payload() + ['form_token' => $token]); }
        finally { Carbon::setTestNow(); }
    }

    private function payload(): array
    {
        return ['name' => 'Alice Example', 'email' => 'alice@example.test', 'subject' => 'Question', 'message' => 'Bonjour', 'website' => ''];
    }
}
