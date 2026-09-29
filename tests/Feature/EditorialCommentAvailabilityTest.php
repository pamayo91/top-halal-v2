<?php

namespace Tests\Feature;

use App\Models\{Article, Comment, Page, User};
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class EditorialCommentAvailabilityTest extends TestCase
{
    use DatabaseMigrations;

    public function test_articles_keep_existing_comments_visible_while_closed_and_reopen_cleanly(): void
    {
        $article = $this->article(true);
        Comment::create(['article_id' => $article->id, 'author_name' => 'Nadia', 'content' => 'Historique conservé', 'status' => 'approved']);
        $user = User::factory()->create();

        $this->get('/article-commentaires')->assertOk()->assertSee('Historique conservé')->assertSee('Laisser un commentaire');
        $this->actingAs($user)->post('/article-commentaires/commentaires', ['name' => 'Amina', 'content' => 'Nouveau commentaire'])->assertRedirect();
        $this->assertDatabaseHas('comments', ['article_id' => $article->id, 'content' => 'Nouveau commentaire', 'status' => 'pending']);

        $article->update(['comments_enabled' => false]);
        $this->get('/article-commentaires')->assertOk()->assertSee('Historique conservé')->assertDontSee('Laisser un commentaire');
        $this->actingAs($user)->post('/article-commentaires/commentaires', ['name' => 'Amina', 'content' => 'Interdit'])->assertForbidden();
        $this->assertDatabaseMissing('comments', ['article_id' => $article->id, 'content' => 'Interdit']);

        $article->update(['comments_enabled' => true]);
        $this->get('/article-commentaires')->assertOk()->assertSee('Historique conservé')->assertSee('Laisser un commentaire');
    }

    public function test_pages_keep_existing_comments_visible_but_reject_direct_posts_when_closed(): void
    {
        $page = Page::create(['legacy_wp_id' => 8912, 'original_title' => 'Page discussion', 'title' => 'Page discussion', 'slug' => 'page-commentaires', 'legacy_url' => '/page-commentaires', 'status' => 'published']);
        Comment::create(['page_id' => $page->id, 'author_name' => 'Nadia', 'content' => 'Commentaire de page', 'status' => 'approved']);
        $user = User::factory()->create();

        $this->get('/page-commentaires')->assertOk()->assertSee('Commentaire de page')->assertSee('Laisser un commentaire');
        $page->update(['comments_enabled' => false]);
        $this->get('/page-commentaires')->assertOk()->assertSee('Commentaire de page')->assertDontSee('Laisser un commentaire');
        $this->actingAs($user)->post('/page-commentaires/commentaires', ['name' => 'Amina', 'content' => 'Interdit'])->assertForbidden();
        $this->assertDatabaseCount('comments', 1);
    }

    public function test_a_closed_content_with_no_published_comments_hides_the_entire_comments_section(): void
    {
        $article = $this->article(false);

        $this->get('/article-commentaires')
            ->assertOk()
            ->assertDontSee('id="commentaires"', false)
            ->assertDontSee('Commentaires (0)')
            ->assertDontSee('Pas encore de commentaire.')
            ->assertDontSee('Laisser un commentaire');
    }

    private function article(bool $commentsEnabled): Article
    {
        return Article::create(['legacy_wp_id' => 8911, 'original_title' => 'Article discussion', 'title' => 'Article discussion', 'slug' => 'article-commentaires', 'legacy_url' => '/article-commentaires', 'status' => 'published', 'comments_enabled' => $commentsEnabled]);
    }
}
