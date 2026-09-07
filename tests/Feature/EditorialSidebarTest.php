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
        self::assertSame(['enabled' => true, 'title' => 'Sélection', 'limit' => 2, 'order' => 4], collect($block)->only(['enabled', 'title', 'limit', 'order'])->all());
    }

    public function test_table_of_contents_anchors_are_deterministic_and_unique(): void
    {
        $sidebar = app(EditorialSidebar::class)->for($this->article(['content_html' => '<h2>Déjà vu !</h2><h2>Déjà vu !</h2><h3>L’été</h3>']));

        self::assertStringContainsString('id="deja-vu"', $sidebar['html']);
        self::assertStringContainsString('id="deja-vu-2"', $sidebar['html']);
        self::assertStringContainsString('id="lete"', $sidebar['html']);
        self::assertCount(3, $sidebar['toc']);
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
