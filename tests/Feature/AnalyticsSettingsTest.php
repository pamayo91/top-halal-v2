<?php

namespace Tests\Feature;

use App\Filament\Pages\SettingsPage;
use App\Models\{Setting, User};
use App\Services\AnalyticsSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AnalyticsSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_analytics_is_disabled_and_unconfigured_by_default(): void
    {
        $settings = app(AnalyticsSettings::class);

        $this->assertFalse($settings->enabled());
        $this->assertNull($settings->measurementId());
        $this->assertFalse($settings->isConfigured());
    }

    public function test_administrator_can_save_a_normalized_valid_measurement_id(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($admin)->test(SettingsPage::class)
            ->assertSet('data.analytics_ga4_enabled', false)
            ->assertSet('data.analytics_ga4_measurement_id', null)
            ->set('data.analytics_ga4_enabled', true)
            ->set('data.analytics_ga4_measurement_id', ' g-ab12cd34ef ')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('settings', ['key' => AnalyticsSettings::ENABLED_KEY, 'group' => 'analytics', 'value' => json_encode(['enabled' => true])]);
        $this->assertDatabaseHas('settings', ['key' => AnalyticsSettings::MEASUREMENT_ID_KEY, 'group' => 'analytics', 'value' => json_encode(['value' => 'G-AB12CD34EF'])]);
        $this->assertTrue(app(AnalyticsSettings::class)->enabled());
        $this->assertSame('G-AB12CD34EF', app(AnalyticsSettings::class)->measurementId());
        $this->assertTrue(app(AnalyticsSettings::class)->isConfigured());
    }

    public function test_invalid_measurement_ids_and_activation_without_one_are_refused(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($admin)->test(SettingsPage::class)
            ->set('data.analytics_ga4_measurement_id', 'UA-12345-6')
            ->call('save')
            ->assertHasFormErrors(['analytics_ga4_measurement_id']);

        Livewire::actingAs($admin)->test(SettingsPage::class)
            ->set('data.analytics_ga4_enabled', true)
            ->set('data.analytics_ga4_measurement_id', null)
            ->call('save')
            ->assertHasFormErrors(['analytics_ga4_measurement_id']);
    }

    public function test_cache_is_invalidated_when_an_analytics_setting_changes(): void
    {
        $settings = app(AnalyticsSettings::class);
        $this->assertFalse($settings->isConfigured());

        Setting::updateOrCreate(['key' => AnalyticsSettings::ENABLED_KEY], ['group' => 'analytics', 'value' => ['enabled' => true]]);
        Setting::updateOrCreate(['key' => AnalyticsSettings::MEASUREMENT_ID_KEY], ['group' => 'analytics', 'value' => ['value' => 'G-AB12CD34EF']]);

        $this->assertTrue($settings->isConfigured());

        Setting::where('key', AnalyticsSettings::ENABLED_KEY)->firstOrFail()->update(['value' => ['enabled' => false]]);

        $this->assertFalse($settings->isConfigured());
    }

    public function test_public_html_and_csp_remain_analytics_free_even_when_configured(): void
    {
        Setting::create(['key' => AnalyticsSettings::ENABLED_KEY, 'group' => 'analytics', 'value' => ['enabled' => true]]);
        Setting::create(['key' => AnalyticsSettings::MEASUREMENT_ID_KEY, 'group' => 'analytics', 'value' => ['value' => 'G-AB12CD34EF']]);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('G-AB12CD34EF')
            ->assertDontSee('googletagmanager.com')
            ->assertDontSee('google-analytics.com')
            ->assertDontSee('dataLayer')
            ->assertHeader('Content-Security-Policy', "default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'; img-src 'self' data: https://*.tile.openstreetmap.org; font-src 'self' data:; connect-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'");
    }
}
