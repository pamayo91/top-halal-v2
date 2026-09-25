<?php

namespace Tests\Feature;

use App\Filament\Resources\ArticleResource\Pages\EditArticle;
use App\Filament\Resources\ArticleResource\Pages\CreateArticle;
use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Filament\Resources\PageResource\Pages\CreatePage;
use App\Models\Article;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
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
}
