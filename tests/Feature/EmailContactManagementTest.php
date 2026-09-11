<?php

namespace Tests\Feature;

use App\Mail\TemplateMailable;
use App\Models\{EmailTemplate, MediaAsset, Setting};
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
        $this->assertSame([['Premier'], ['Deuxième <script>alert(1)</script>']], $rendered['body_paragraphs']);
        $this->assertStringNotContainsString('\\n', $html);
        $this->assertSame(2, substr_count($html, 'data-email-body-paragraph'));
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    public function test_renderer_safely_builds_paragraphs_for_html_preview_and_delivery(): void
    {
        $renderer = app(EmailTemplateRenderer::class);
        $email = $renderer->render('password_changed', ['site_name' => 'Top Halal', 'user_name' => 'Alice']);
        $global = app(EmailGlobalSettings::class)->forRender();
        $html = view('emails.transactional', compact('email', 'global'))->render();

        $this->assertSame([['Bonjour Alice,'], ['Votre mot de passe Top Halal vient d’être modifié. Si vous n’êtes pas à l’origine de cette action, contactez la modération.']], $email['body_paragraphs']);
        $this->assertSame(2, substr_count($html, 'data-email-body-paragraph'));
        $this->assertStringNotContainsString('<br><br>', $html);
        $this->assertStringContainsString('padding:36px 32px 76px', $html);
        $deliveryHtml = (new TemplateMailable('password_changed', ['site_name' => 'Top Halal', 'user_name' => 'Alice']))->render();
        $this->assertSame(2, substr_count($deliveryHtml, 'data-email-body-paragraph'));
        $this->assertStringContainsString('Bonjour Alice,', $deliveryHtml);

        EmailTemplate::create(['key' => 'contact_confirmation', 'subject' => 'Sujet', 'body' => "Une ligne\navec un retour simple.\n\nUn second paragraphe.\n\nUn troisième paragraphe."]);
        $threeParagraphs = $renderer->render('contact_confirmation', []);
        $this->assertSame([['Une ligne', 'avec un retour simple.'], ['Un second paragraphe.'], ['Un troisième paragraphe.']], $threeParagraphs['body_paragraphs']);
    }

    public function test_global_layout_inherits_footer_presentation_and_site_design(): void
    {
        Setting::updateOrCreate(['key' => 'footer_navigation'], ['group' => 'navigation', 'value' => ['introduction' => 'Le guide des restaurants halal en France']]);
        app(EmailGlobalSettings::class)->update(['display_name' => 'Halal Courrier', 'footer_text' => "Une question ?\nÀ très bientôt !\nL'équipe Halal Courrier", 'primary_color' => '#ffffff', 'show_current_year' => true, 'footer_additional_text' => 'Obsolète']);
        $email = app(EmailTemplateRenderer::class)->render('password_reset', ['site_name' => 'Top Halal', 'user_name' => 'Alice', 'reset_url' => 'https://example.test/reset']);
        $global = app(EmailGlobalSettings::class)->forRender();
        $html = view('emails.transactional', compact('email', 'global'))->render();
        $text = view('emails.transactional-text', compact('email', 'global'))->render();

        $this->assertStringContainsString('Halal Courrier', $html);
        $this->assertStringContainsString('Le guide des restaurants halal en France', $html);
        $this->assertStringContainsString(config('design.primary'), $html);
        $this->assertStringContainsString('https://example.test/reset', $html);
        $this->assertStringContainsString('bgcolor="' . config('design.primary') . '"', $html);
        $this->assertStringContainsString('margin:28px 0 0;text-align:center', $html);
        $this->assertStringContainsString('border-collapse:separate;border-spacing:0;border-radius:12px;overflow:hidden', $html);
        $this->assertStringContainsString('<v:roundrect', $html);
        $this->assertStringContainsString('Une question ?', $text);
        $this->assertStringContainsString('À très bientôt !', $text);
        $this->assertStringContainsString("L'équipe Halal Courrier", $text);
        $this->assertStringNotContainsString('©', $html);
        $this->assertStringNotContainsString('Obsolète', $html);
        $this->assertStringContainsString('Bonjour Alice', $html);
    }

    public function test_footer_presentation_change_and_logo_are_reflected_in_a_rendered_mailable(): void
    {
        $logo = MediaAsset::create(['original_path' => 'email-logo.webp', 'mime' => 'image/webp', 'width' => 320, 'height' => 120, 'bytes' => 1000, 'checksum' => str_repeat('a', 64), 'alt_text' => 'Logo Top Halal']);
        Setting::updateOrCreate(['key' => 'footer_navigation'], ['group' => 'navigation', 'value' => ['introduction' => 'Première présentation']]);
        app(EmailGlobalSettings::class)->update(['display_name' => 'Nouvelle identité', 'logo_media_asset_id' => $logo->id]);
        $html = (new TemplateMailable('contact_confirmation', ['contact_name' => 'Alice']))->render();

        $this->assertStringContainsString($logo->deliveryUrl(480), $html);
        $this->assertStringContainsString('Première présentation', $html);
        $this->assertStringNotContainsString('<h1', $html);

        Setting::where('key', 'footer_navigation')->firstOrFail()->update(['value' => ['introduction' => 'Présentation mise à jour']]);
        $updated = (new TemplateMailable('contact_confirmation', ['contact_name' => 'Alice']))->render();
        $this->assertStringContainsString('Présentation mise à jour', $updated);
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
