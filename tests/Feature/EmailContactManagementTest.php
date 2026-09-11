<?php

namespace Tests\Feature;

use App\Mail\TemplateMailable;
use App\Models\{EmailTemplate, Setting};
use App\Services\{EmailGlobalSettings, EmailTemplateRenderer, MailSettings};
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
        $this->post('/contact', ['name' => 'Alice', 'email' => 'alice@top-halal.fr', 'subject' => 'Question', 'message' => 'Texte', 'website' => ''])->assertRedirect();
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
        $this->assertStringContainsString('<Alice>', $fallback['body']);
        $this->assertStringContainsString('&lt;Alice&gt;', view('emails.transactional', ['email' => $fallback, 'global' => app(EmailGlobalSettings::class)->forRender()])->render());
        EmailTemplate::create(['key' => 'contact_confirmation', 'subject' => 'Bonjour {{ contact_name }} {{ unknown }}', 'body' => 'Texte {{ site_name }}']);
        $rendered = $renderer->render('contact_confirmation', ['site_name' => 'Top Halal', 'contact_name' => 'Alice']);
        $this->assertSame('Bonjour Alice {{ unknown }}', $rendered['subject']); $this->assertSame('Texte Top Halal', $rendered['body']);
    }

    public function test_template_rendering_normalises_legacy_newline_escapes_and_escapes_html(): void
    {
        EmailTemplate::create(['key' => 'contact_confirmation', 'subject' => 'Sujet', 'body' => 'Premier\\n\\nDeuxième <script>alert(1)</script>']);

        $rendered = app(EmailTemplateRenderer::class)->render('contact_confirmation', []);
        $html = view('emails.transactional', ['email' => $rendered, 'global' => app(EmailGlobalSettings::class)->forRender()])->render();

        $this->assertSame("Premier\n\nDeuxième <script>alert(1)</script>", $rendered['body']);
        $this->assertStringNotContainsString('\\n', $html);
        $this->assertStringContainsString('Premier' . "\n\n" . 'Deuxième', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    public function test_global_layout_is_used_by_preview_html_and_text_email(): void
    {
        app(EmailGlobalSettings::class)->update(['display_name' => 'Halal Courrier', 'primary_color' => '#123456', 'footer_text' => 'Le footer', 'show_current_year' => true, 'footer_additional_text' => 'Informations complémentaires']);
        $email = app(EmailTemplateRenderer::class)->render('contact_confirmation', ['site_name' => 'Top Halal', 'contact_name' => 'Alice', 'contact_subject' => 'Question']);
        $global = app(EmailGlobalSettings::class)->forRender();
        $html = view('emails.transactional', compact('email', 'global'))->render();
        $text = view('emails.transactional-text', compact('email', 'global'))->render();

        $this->assertStringContainsString('Halal Courrier', $html);
        $this->assertStringContainsString('#123456', $html);
        $this->assertStringContainsString('Le footer', $html);
        $this->assertStringContainsString((string) now()->year, $html);
        $this->assertStringContainsString('Informations complémentaires', $text);
        $this->assertStringContainsString('Bonjour Alice', $html);
    }

    public function test_global_settings_change_is_reflected_in_a_rendered_mailable(): void
    {
        app(EmailGlobalSettings::class)->update(['display_name' => 'Nouvelle identité', 'footer_text' => 'Nouveau footer']);
        $html = (new TemplateMailable('contact_confirmation', ['contact_name' => 'Alice']))->render();

        $this->assertStringContainsString('Nouvelle identité', $html);
        $this->assertStringContainsString('Nouveau footer', $html);
    }

    public function test_mail_settings_encrypt_and_preserve_an_existing_password(): void
    {
        $settings = app(MailSettings::class); $settings->update(['mailer' => 'smtp', 'host' => 'smtp.example.test', 'password' => 'secret-value']);
        $stored = Setting::where('key', 'mail_settings')->value('value'); $this->assertNotSame('secret-value', $stored['password']); $this->assertSame('secret-value', Crypt::decryptString($stored['password']));
        $settings->update(['mailer' => 'smtp', 'host' => 'smtp.example.test', 'password' => '']);
        $this->assertSame('secret-value', Crypt::decryptString(Setting::where('key', 'mail_settings')->value('value')['password']));
    }

    public function test_mail_settings_translate_the_ssl_choice_to_the_smtps_transport_scheme(): void
    {
        app(MailSettings::class)->update(['mailer' => 'smtp', 'host' => 'smtp.example.test', 'port' => 465, 'encryption' => 'ssl']);

        app(MailSettings::class)->apply();

        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
    }

    public function test_mail_settings_always_use_smtp_and_discard_the_obsolete_administrator_address(): void
    {
        app(MailSettings::class)->update(['mailer' => 'log', 'host' => 'smtp.example.test', 'admin_email' => 'admin@example.test']);

        $stored = Setting::where('key', 'mail_settings')->value('value');

        $this->assertSame('smtp', $stored['mailer']);
        $this->assertArrayNotHasKey('admin_email', $stored);
        $this->assertSame('smtp', app(MailSettings::class)->apply());
    }
}
