<?php

namespace Tests\Feature;

use App\Filament\Pages\HomepageSettingsPage;
use App\Models\{Page, Setting, User};
use App\Services\HomepageSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HomepageSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_renders_the_safe_defaults_from_its_dedicated_settings(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Trouvez votre restaurant halal, simplement.')
            ->assertSee('Trouvez facilement un restaurant halal')
            ->assertSee('Le halal au quotidien, et bien plus encore')
            ->assertSee('Des informations pour mieux choisir')
            ->assertSee('Vous connaissez une bonne adresse ?')
            ->assertSee('<title>Top Halal — Trouver un restaurant halal</title>', false)
            ->assertSee('rel="canonical" href="'.route('home').'"', false);
    }

    public function test_custom_copy_and_seo_are_escaped_and_disabled_blocks_are_not_rendered(): void
    {
        app(HomepageSettings::class)->save([
            'seo' => ['title' => 'Titre <script>SEO</script>', 'description' => 'Description personnalisée'],
            'hero' => ['title' => 'Un hero personnalisé'],
            'restaurant_block' => ['enabled' => false],
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Un hero personnalisé')
            ->assertSee('Description personnalisée', false)
            ->assertDontSee('Trouvez facilement un restaurant halal')
            ->assertDontSee('<script>SEO</script>', false)
            ->assertSee('&lt;script&gt;SEO&lt;/script&gt;', false);
    }

    public function test_administrator_can_save_homepage_sections_without_exposing_layout_controls(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Livewire::actingAs($admin)->test(HomepageSettingsPage::class)
            ->assertSet('data.guide.title', 'Le guide Top Halal')
            ->set('data.guide.title', 'Guide personnalisé')
            ->set('data.submission_cta.text', 'Une invitation personnalisée.')
            ->call('save')
            ->assertHasNoFormErrors();

        $value = Setting::where('key', HomepageSettings::SETTINGS_KEY)->value('value');
        $this->assertSame('Guide personnalisé', $value['guide']['title']);
        $this->assertSame('Une invitation personnalisée.', $value['submission_cta']['text']);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'homepage.updated']);
        $this->actingAs($admin)->get('/admin/homepage')->assertOk()->assertSee("Page d'accueil")->assertDontSee('Couleur')->assertDontSee('CSS');
    }

    public function test_homepage_editor_validates_required_copy(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Livewire::actingAs($admin)->test(HomepageSettingsPage::class)
            ->set('data.seo.title', '')
            ->call('save')
            ->assertHasFormErrors(['seo.title' => 'required']);
    }

    public function test_legacy_homepage_editorial_record_is_not_the_public_homepage_route(): void
    {
        Page::create(['legacy_wp_id' => 12755, 'original_title' => 'Home', 'title' => 'Home', 'slug' => 'home', 'legacy_url' => '/home/', 'content_html' => 'Ancien contenu WordPress', 'status' => 'published']);
        $this->get('/')->assertOk()->assertDontSee('Ancien contenu WordPress');
    }
}
