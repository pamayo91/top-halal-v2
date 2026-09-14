<?php

namespace Tests\Feature;

use App\Filament\Pages\SettingsPage;
use App\Models\{MediaAsset, Setting, User};
use App\Services\ErrorPageSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ErrorPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_url_keeps_its_404_status_and_renders_the_full_default_page(): void
    {
        $asset = $this->illustration('a');
        Setting::create(['key' => ErrorPageSettings::DEFAULT_ILLUSTRATION_KEY, 'group' => 'error_pages', 'value' => ['media_asset_id' => $asset->id]]);

        $response = $this->get('/une-page-introuvable');

        $response->assertNotFound()
            ->assertSee('noindex,follow', false)
            ->assertSee(ErrorPageSettings::DEFAULT_TITLE)
            ->assertSee(ErrorPageSettings::DEFAULT_TEXT)
            ->assertSee('href="'.route('home').'"', false)
            ->assertSee('href="'.route('restaurants.index').'"', false)
            ->assertSee('Des milliers de restaurants')
            ->assertSee('Toutes vos cuisines préférées')
            ->assertSee('Des avis authentiques')
            ->assertSee('Une recherche en toute confiance')
            ->assertSee($asset->deliveryUrl(960), false)
            ->assertDontSee('wp-content', false);
    }

    public function test_custom_404_copy_and_media_override_the_defaults(): void
    {
        $fallback = $this->illustration('b');
        $custom = $this->illustration('c');
        Setting::create(['key' => ErrorPageSettings::DEFAULT_ILLUSTRATION_KEY, 'group' => 'error_pages', 'value' => ['media_asset_id' => $fallback->id]]);
        Setting::create(['key' => ErrorPageSettings::SETTINGS_KEY, 'group' => 'error_pages', 'value' => ['illustration_media_asset_id' => $custom->id, 'title' => 'Titre personnalisé', 'text' => 'Texte personnalisé']]);

        $this->get('/introuvable-personnalisee')->assertNotFound()
            ->assertSee('Titre personnalisé')
            ->assertSee('Texte personnalisé')
            ->assertSee($custom->deliveryUrl(960), false)
            ->assertDontSee($fallback->deliveryUrl(960), false);
    }

    public function test_empty_404_settings_fall_back_to_the_default_copy_and_illustration(): void
    {
        $fallback = $this->illustration('d');
        Setting::create(['key' => ErrorPageSettings::DEFAULT_ILLUSTRATION_KEY, 'group' => 'error_pages', 'value' => ['media_asset_id' => $fallback->id]]);
        Setting::create(['key' => ErrorPageSettings::SETTINGS_KEY, 'group' => 'error_pages', 'value' => ['title' => '', 'text' => '', 'illustration_media_asset_id' => null]]);

        $this->get('/introuvable-vide')->assertNotFound()
            ->assertSee(ErrorPageSettings::DEFAULT_TITLE)
            ->assertSee(ErrorPageSettings::DEFAULT_TEXT)
            ->assertSee($fallback->deliveryUrl(960), false);
    }

    public function test_settings_page_persists_only_the_allowed_404_customizations(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);
        $asset = $this->illustration('e');

        Livewire::actingAs($admin)->test(SettingsPage::class)
            ->set('data.error_404.illustration_media_asset_id', $asset->id)
            ->set('data.error_404.title', 'Un titre BO')
            ->set('data.error_404.text', 'Un texte BO')
            ->call('save');

        $this->assertSame([
            'illustration_media_asset_id' => $asset->id,
            'title' => 'Un titre BO',
            'text' => 'Un texte BO',
        ], Setting::where('key', ErrorPageSettings::SETTINGS_KEY)->value('value'));
        $this->assertSame(['media_asset_id' => $asset->id], Setting::where('key', ErrorPageSettings::DEFAULT_ILLUSTRATION_KEY)->value('value'));
    }

    private function illustration(string $seed): MediaAsset
    {
        $asset = MediaAsset::create([
            'original_path' => "media/originals/404-{$seed}.png",
            'mime' => 'image/png',
            'width' => 1200,
            'height' => 900,
            'bytes' => 100,
            'checksum' => str_repeat($seed, 64),
            'status' => 'ready',
        ]);
        $asset->variants()->create(['format' => 'webp', 'width' => 960, 'height' => 720, 'path' => "media/variants/404-{$seed}-960.webp"]);

        return $asset;
    }
}
