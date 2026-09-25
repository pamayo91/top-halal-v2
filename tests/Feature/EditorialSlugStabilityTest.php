<?php

namespace Tests\Feature;

use App\Filament\Resources\ArticleResource\Pages\EditArticle;
use App\Filament\Resources\ArticleResource\Pages\CreateArticle;
use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Filament\Resources\PageResource\Pages\CreatePage;
use App\Models\Article;
use App\Models\Page;
use App\Models\RedirectRule;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class EditorialSlugStabilityTest extends TestCase
{
    use DatabaseMigrations;

    public function test_editing_a_page_title_keeps_its_slug_and_public_url_strictly_identical(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Livewire::actingAs($admin)
            ->test(CreatePage::class)
            ->set('data.title', 'Guide halal initial')
            ->assertSet('data.slug', 'guide-halal-initial')
            ->fillForm([
                'status' => 'published',
                'content_html' => '<p>Contenu de test.</p>',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $page = Page::where('slug', 'guide-halal-initial')->firstOrFail();
        $slug = $page->slug;
        $url = route('editorial.show', $slug);

        Livewire::actingAs($admin)
            ->test(EditPage::class, ['record' => $page->getRouteKey()])
            ->fillForm(['title' => 'Guide halal renommé'])
            ->call('save')
            ->assertHasNoFormErrors();

        $page->refresh();

        $this->assertSame($slug, $page->slug);
        $this->assertSame($url, route('editorial.show', $page->slug));
        $this->get($url)->assertOk()->assertSee('Guide halal renommé');
    }

    public function test_editing_an_article_title_keeps_its_slug_and_public_url_strictly_identical(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Livewire::actingAs($admin)
            ->test(CreateArticle::class)
            ->set('data.title', 'Article halal initial')
            ->assertSet('data.slug', 'article-halal-initial')
            ->fillForm([
                'status' => 'published',
                'content_html' => '<p>Contenu de test.</p>',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $article = Article::where('slug', 'article-halal-initial')->firstOrFail();
        $slug = $article->slug;
        $url = route('editorial.show', $slug);

        Livewire::actingAs($admin)
            ->test(EditArticle::class, ['record' => $article->getRouteKey()])
            ->fillForm(['title' => 'Article halal renommé'])
            ->call('save')
            ->assertHasNoFormErrors();

        $article->refresh();

        $this->assertSame($slug, $article->slug);
        $this->assertSame($url, route('editorial.show', $article->slug));
        $this->get($url)->assertOk()->assertSee('Article halal renommé');
    }

    public function test_an_administrator_can_manually_change_a_page_slug_and_get_an_exact_301_visible_in_redirects(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $page = $this->page('page-ancienne-url');

        Livewire::actingAs($admin)
            ->test(EditPage::class, ['record' => $page->getRouteKey()])
            ->fillForm(['slug' => 'page-nouvelle-url'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('page-nouvelle-url', $page->fresh()->slug);
        $this->assertDatabaseHas('redirect_rules', [
            'source_path' => '/page-ancienne-url',
            'destination' => '/page-nouvelle-url',
            'match_type' => 'exact',
            'status_code' => 301,
            'is_active' => true,
            'origin' => 'editorial_slug_change',
        ]);
        $this->get('/page-ancienne-url')->assertRedirect('/page-nouvelle-url')->assertStatus(301);
        $this->get('/page-nouvelle-url')->assertOk();
        $this->actingAs($admin)->get('/admin/redirect-rules')->assertOk()->assertSee('/page-ancienne-url')->assertSee('/page-nouvelle-url');
    }

    public function test_an_administrator_can_manually_change_an_article_slug_and_get_an_exact_301(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $article = $this->article('article-ancienne-url');

        Livewire::actingAs($admin)
            ->test(EditArticle::class, ['record' => $article->getRouteKey()])
            ->fillForm(['slug' => 'article-nouvelle-url'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('article-nouvelle-url', $article->fresh()->slug);
        $this->assertDatabaseHas('redirect_rules', [
            'source_path' => '/article-ancienne-url',
            'destination' => '/article-nouvelle-url',
            'match_type' => 'exact',
            'status_code' => 301,
            'is_active' => true,
            'origin' => 'editorial_slug_change',
        ]);
        $this->get('/article-ancienne-url')->assertRedirect('/article-nouvelle-url')->assertStatus(301);
        $this->get('/article-nouvelle-url')->assertOk();
    }

    public function test_editorial_slug_changes_reject_content_duplicates_redirect_conflicts_and_loops(): void
    {
        $page = $this->page('page-existante');

        try {
            $this->article('page-existante');
            $this->fail('A Page and an Article cannot share a public slug.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('slug', $exception->errors());
        }

        $conflicted = $this->page('page-conflit');
        RedirectRule::create(['source_path' => '/page-cible-deja-redirigee', 'match_type' => 'exact', 'destination' => '/ailleurs', 'status_code' => 301, 'is_active' => true]);
        try {
            $conflicted->update(['slug' => 'page-cible-deja-redirigee']);
            $this->fail('A redirect source cannot be reused as a content slug.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('slug', $exception->errors());
        }
        $this->assertSame('page-conflit', $conflicted->fresh()->slug);

        $sourceConflicted = $this->page('page-source-conflit');
        RedirectRule::create(['source_path' => '/page-source-conflit', 'match_type' => 'exact', 'destination' => '/ailleurs', 'status_code' => 301, 'is_active' => true]);
        try {
            $sourceConflicted->update(['slug' => 'page-source-nouvelle']);
            $this->fail('An incompatible redirect at the former URL must not be overwritten.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('slug', $exception->errors());
        }
        $this->assertSame('page-source-conflit', $sourceConflicted->fresh()->slug);

        $looping = $this->page('page-boucle-ancienne');
        RedirectRule::create(['source_path' => '^page-boucle-nouvelle$', 'match_type' => 'regex', 'destination' => '/page-boucle-ancienne', 'status_code' => 301, 'is_active' => true]);
        try {
            $looping->update(['slug' => 'page-boucle-nouvelle']);
            $this->fail('A redirect loop must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('slug', $exception->errors());
        }
        $this->assertSame('page-boucle-ancienne', $looping->fresh()->slug);
        $this->assertSame('page-existante', $page->fresh()->slug);
    }

    private function page(string $slug): Page
    {
        return Page::create([
            'legacy_wp_id' => random_int(1_000_000_000, 2_000_000_000),
            'original_title' => $slug,
            'title' => $slug,
            'slug' => $slug,
            'legacy_url' => '/'.$slug,
            'content_html' => '<p>Contenu de test.</p>',
            'status' => 'published',
        ]);
    }

    private function article(string $slug): Article
    {
        return Article::create([
            'legacy_wp_id' => random_int(1_000_000_000, 2_000_000_000),
            'original_title' => $slug,
            'title' => $slug,
            'slug' => $slug,
            'legacy_url' => '/'.$slug,
            'content_html' => '<p>Contenu de test.</p>',
            'status' => 'published',
        ]);
    }
}
