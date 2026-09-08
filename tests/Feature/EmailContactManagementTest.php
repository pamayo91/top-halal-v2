<?php

namespace Tests\Feature;

use App\Mail\TemplateMailable;
use App\Models\{EmailTemplate, Setting};
use App\Services\{EmailTemplateRenderer, MailSettings};
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailContactManagementTest extends TestCase
{
    use DatabaseMigrations;

    public function test_contact_is_persisted_and_queues_admin_and_optional_confirmation(): void
    {
        Mail::fake();
        Setting::create(['key' => 'contact_settings', 'group' => 'contact', 'value' => ['recipient' => 'admin@top-halal.fr', 'send_confirmation' => true, 'success_message' => 'Merci']]);
        $this->post('/contact', ['name' => 'Alice', 'email' => 'alice@top-halal.fr', 'subject' => 'Question', 'message' => '<b>Texte</b>', 'website' => ''])->assertRedirect(route('contact.create'))->assertSessionHas('status', 'Merci');
        $this->assertDatabaseHas('contact_messages', ['name' => 'Alice', 'email' => 'alice@top-halal.fr', 'status' => 'new']); $this->assertDatabaseCount('email_delivery_logs', 2);
        Mail::assertQueued(TemplateMailable::class, fn (TemplateMailable $mail) => $mail->templateKey === 'contact_admin' && $mail->replyToAddress === 'alice@top-halal.fr');
        Mail::assertQueued(TemplateMailable::class, fn (TemplateMailable $mail) => $mail->templateKey === 'contact_confirmation');
    }

    public function test_contact_confirmation_can_be_disabled_without_losing_the_message(): void
    {
        Mail::fake(); Setting::create(['key' => 'contact_settings', 'group' => 'contact', 'value' => ['recipient' => 'admin@top-halal.fr', 'send_confirmation' => false]]);
        $this->post('/contact', ['name' => 'Alice', 'email' => 'alice@top-halal.fr', 'subject' => 'Question', 'message' => 'Texte', 'website' => '])->assertRedirect();
        $this->assertDatabaseCount('contact_messages', 1); $this->assertDatabaseCount('email_delivery_logs', 1); Mail::assertNotQueued(TemplateMailable::class, fn (TemplateMailable $mail) => $mail->templateKey === 'contact_confirmation');
    }

    public function test_contact_validates_honeypot_and_required_fields(): void
    {
        $this->post('/contact', ['website' => 'bot'])->assertSessionHasErrors('message'); $this->assertDatabaseCount('contact_messages', 0);
        $this->post('/contact', ['name' => '', 'email' => 'wrong', 'subject' => '', 'message' => ''])->assertSessionHasErrors(['name', 'email', 'subject', 'message']);
    }

    public function test_templates_fallback_override_and_unknown_variable_are_safe(): void
    {
        $renderer = app(EmailTemplateRenderer::class);
        $fallback = $renderer->render('contact_confirmation', ['site_name' => 'Top Halal', 'contact_name' => '<Alice>', 'contact_subject' => 'Bonjour']);
        $this->assertStringContainsString('&lt;Alice&gt;', $fallback['body']);
        EmailTemplate::create(['key' => 'contact_confirmation', 'subject' => 'Bonjour {{ contact_name }} {{ unknown }}', 'body' => 'Texte {{ site_name }}', 'is_active' => true]);
        $rendered = $renderer->render('contact_confirmation', ['site_name' => 'Top Halal', 'contact_name' => 'Alice']);
        $this->assertSame('Bonjour Alice {{ unknown }}', $rendered['subject']); $this->assertSame('Texte Top Halal', $rendered['body']);
        EmailTemplate::where('key', 'contact_confirmation')->update(['is_active' => false]); $this->assertFalse($renderer->render('contact_confirmation', [])['active']);
    }

    public function test_mail_settings_encrypt_and_preserve_an_existing_password(): void
    {
        $settings = app(MailSettings::class); $settings->update(['mailer' => 'smtp', 'host' => 'smtp.example.test', 'password' => 'secret-value']);
        $stored = Setting::where('key', 'mail_settings')->value('value'); $this->assertNotSame('secret-value', $stored['password']); $this->assertSame('secret-value', Crypt::decryptString($stored['password']));
        $settings->update(['mailer' => 'smtp', 'host' => 'smtp.example.test', 'password' => '']);
        $this->assertSame('secret-value', Crypt::decryptString(Setting::where('key', 'mail_settings')->value('value')['password']));
    }
}
