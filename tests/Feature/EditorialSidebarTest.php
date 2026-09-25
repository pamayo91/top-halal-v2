<?php

namespace Tests\Feature;

use App\Models\{Article, Page, Setting};
use App\Services\EditorialSidebar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditorialSidebarTest extends TestCase
{
    use RefreshDatabase;
    public function test_articles_enable_the_shared_sidebar_by_default_but_pages_do_not(): void
    {
        $article = $this->article(['content_html' => '<h2>Premier titre</h2><h2>Second titre</h2>']);
        $page = $this->page(['content_html' => '<h2>Premier titre</h2><h2>Second titre</h2>']);

        self::assertTrue(app(EditorialSidebar::class)->for($article)['enabled']);
        self::assertFalse(app(EditorialSidebar::class)->for($page)['enabled']);
    }

    public function test_sparse_content_overrides_inherit_the_global_block_configuration(): void
    {
        Setting::create(['key' => 'editorial_sidebar_articles', 'group' => 'editorial', 'value' => ['blocks' => [['type' => 'articles', 'enabled' => true, 'title' => 'À lire', 'order' => 4, 'limit' => 3]]]]);
        $article = $this->article(['editorial_sidebar_overrides' => ['blocks' => [['type' => 'articles', 'title' => 'Sélection', 'limit' => 2]]]]);

        $block = collect(app(EditorialSidebar::class)->for($article)['blocks'])->firstWhere('type', 'articles');
        self::assertTrue($block['enabled']);
        self::assertSame('Sélection', $block['title']);
        self::assertSame(2, $block['limit']);
        self::assertSame(4, $block['order']);
    }

    public function test_table_of_contents_anchors_are_deterministic_and_unique(): void
    {
        $sidebar = app(EditorialSidebar::class)->for($this->article(['content_html' => '<h2>Déjà vu !</h2><h2>Déjà vu !</h2><h3>L’été</h3>']));

        self::assertStringContainsString('id="deja-vu"', $sidebar['html']);
        self::assertStringContainsString('id="deja-vu-2"', $sidebar['html']);
        self::assertStringContainsString('id="lete"', $sidebar['html']);
        self::assertCount(3, $sidebar['toc']);
    }

    public function test_desktop_table_of_contents_keeps_its_complete_ssr_list_and_compact_controls(): void
    {
        $article = $this->article(['content_html' => '<h2>Premier titre</h2><h2>Second titre</h2>']);

        $this->get('/'.$article->slug)
            ->assertOk()
            ->assertSee('data-sticky-toc', false)
            ->assertSee('data-toc-current', false)
            ->assertSee('data-toc-toggle', false)
            ->assertSee('Afficher le sommaire')
            ->assertSee('href="#premier-titre"', false)
            ->assertSee('href="#second-titre"', false);
    }

    private function article(array $attributes = []): Article
    {
        return Article::create(array_merge(['legacy_wp_id' => random_int(1, 999999999), 'original_title' => 'Article', 'title' => 'Article', 'slug' => 'article-'.random_int(1, 999999999), 'legacy_url' => '/article', 'status' => 'published'], $attributes));
    }

    private function page(array $attributes = []): Page
    {
        return Page::create(array_merge(['legacy_wp_id' => random_int(1, 999999999), 'original_title' => 'Page', 'title' => 'Page', 'slug' => 'page-'.random_int(1, 999999999), 'legacy_url' => '/page', 'status' => 'published'], $attributes));
    }
}
