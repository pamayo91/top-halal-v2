<?php

namespace Tests\Feature;

use App\Models\{EmailTemplate, Setting};
use App\Services\{EmailTemplateRenderer, MailSettings};
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class EmailContactManagementTest extends TestCase
{
    use DatabaseMigrations;

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
