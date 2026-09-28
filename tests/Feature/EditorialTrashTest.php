<?php

namespace Tests\Feature;

use App\Models\{Article, Page, RedirectRule};
use App\Services\EditorialTrash;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EditorialTrashTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_article_gets_an_audited_301_rule_and_can_be_restored(): void
    {
        $article = $this->article('article-corbeille-'.random_int(1000, 9999));
        app(EditorialTrash::class)->trash($article, 301, '/destination');

        $this->assertSoftDeleted('articles', ['id' => $article->id]);
        $rule = RedirectRule::where('related_type', 'article')->where('related_id', $article->id)->firstOrFail();
        $this->assertSame(301, (int) $rule->status_code);
        $this->assertSame('/destination', $rule->destination);

        app(EditorialTrash::class)->restore(Article::withTrashed()->findOrFail($article->id));
        $this->assertNotNull(Article::find($article->id));
        $this->assertFalse($rule->fresh()->is_active);
    }

    public function test_404_and_410_are_terminal_and_destinations_are_rejected(): void
    {
        $article = $this->article('article-404-'.random_int(1000, 9999));
        app(EditorialTrash::class)->trash($article, 404);
        $this->assertNull(RedirectRule::where('related_id', $article->id)->value('destination'));

        $page = $this->page('page-410-'.random_int(1000, 9999));
        app(EditorialTrash::class)->trash($page, 410);
        $this->assertSame(410, (int) RedirectRule::where('related_type', 'page')->where('related_id', $page->id)->value('status_code'));

        $this->expectException(ValidationException::class);
        app(EditorialTrash::class)->trash($this->article('article-invalid-'.random_int(1000, 9999)), 301);
    }

    public function test_302_is_preserved_after_force_deletion(): void
    {
        $page = $this->page('page-302-'.random_int(1000, 9999));
        app(EditorialTrash::class)->trash($page, 302, '/temporary-target');
        app(EditorialTrash::class)->forceDelete(Page::withTrashed()->findOrFail($page->id));

        $this->assertDatabaseMissing('pages', ['id' => $page->id]);
        $this->assertDatabaseHas('redirect_rules', ['related_type' => 'page', 'related_id' => $page->id, 'status_code' => 302, 'is_active' => 1, 'destination' => '/temporary-target']);
    }

    public function test_unpublished_content_does_not_create_an_seo_rule(): void
    {
        $article = $this->article('article-brouillon-'.random_int(1000, 9999), ['status' => 'draft']);
        app(EditorialTrash::class)->trash($article, 410);
        $this->assertDatabaseMissing('redirect_rules', ['related_id' => $article->id]);
    }

    private function article(string $slug, array $extra = []): Article
    {
        $attributes = array_merge(['legacy_wp_id' => random_int(100000, 999999), 'original_title' => $slug, 'title' => $slug, 'slug' => $slug, 'legacy_url' => '/'.$slug, 'status' => 'published'], $extra);
        $id = DB::table('articles')->insertGetId($attributes + ['created_at' => now(), 'updated_at' => now()]);
        return Article::findOrFail($id);
    }

    private function page(string $slug): Page
    {
        $id = DB::table('pages')->insertGetId(['legacy_wp_id' => random_int(100000, 999999), 'original_title' => $slug, 'title' => $slug, 'slug' => $slug, 'legacy_url' => '/'.$slug, 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
        return Page::findOrFail($id);
    }
}
