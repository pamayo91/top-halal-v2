<?php

namespace Tests\Feature;

use App\Models\{Article, Comment, User};
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class CommentThreadsTest extends TestCase
{
    use DatabaseMigrations;

    private Article $article;

    protected function setUp(): void
    {
        parent::setUp();
        $this->article = Article::create([
            'legacy_wp_id' => 9901,
            'original_title' => 'Discussion',
            'title' => 'Discussion',
            'slug' => 'discussion',
            'legacy_url' => '/discussion',
            'status' => 'published',
        ]);
    }

    public function test_it_paginates_twenty_root_threads_but_keeps_their_approved_replies_together(): void
    {
        $roots = collect(range(1, 21))->map(function (int $number): Comment {
            return Comment::create([
                'article_id' => $this->article->id,
                'author_name' => "Auteur {$number}",
                'content' => "Racine {$number}",
                'status' => 'approved',
                'created_at' => now()->subMinutes($number),
            ]);
        });
        $reply = Comment::create(['article_id' => $this->article->id, 'parent_id' => $roots->first()->id, 'author_name' => 'Répondant', 'content' => 'Réponse conservée', 'status' => 'approved']);
        Comment::create(['article_id' => $this->article->id, 'parent_id' => $reply->id, 'author_name' => 'Second répondant', 'content' => 'Réponse au second niveau', 'status' => 'approved']);
        Comment::create(['article_id' => $this->article->id, 'parent_id' => $roots->first()->id, 'author_name' => 'Non publié', 'content' => 'Invisible', 'status' => 'pending']);

        $this->get('/discussion')
            ->assertOk()
            ->assertSee('Commentaires (23)')
            ->assertSee('Racine 1')
            ->assertSee('Racine 20')
            ->assertDontSee('Racine 21')
            ->assertSee('Réponse conservée')
            ->assertSee('Réponse au second niveau')
            ->assertDontSee('Invisible')
            ->assertSee('href="'.route('editorial.show', ['slug' => 'discussion', 'comments_page' => 2]).'#commentaires"', false)
            ->assertSee('rel="canonical" href="'.route('editorial.show', 'discussion').'"', false);

        $this->get('/discussion?comments_page=2')
            ->assertOk()
            ->assertSee('Racine 21')
            ->assertDontSee('Racine 20')
            ->assertSee('rel="canonical" href="'.route('editorial.show', 'discussion').'"', false);
    }

    public function test_a_reply_parent_must_be_an_approved_comment_on_the_same_editorial_content(): void
    {
        $other = Article::create(['legacy_wp_id' => 9902, 'original_title' => 'Autre', 'title' => 'Autre', 'slug' => 'autre-discussion', 'legacy_url' => '/autre-discussion', 'status' => 'published']);
        $foreign = Comment::create(['article_id' => $other->id, 'author_name' => 'Autre', 'content' => 'Autre contenu', 'status' => 'approved']);
        $pending = Comment::create(['article_id' => $this->article->id, 'author_name' => 'En attente', 'content' => 'En attente', 'status' => 'pending']);
        $user = User::factory()->create();

        foreach ([$foreign->id, $pending->id, 999999] as $parentId) {
            $this->actingAs($user)->post('/discussion/commentaires', [
                'name' => 'Amina', 'content' => 'Une réponse valide en apparence.', 'parent_id' => $parentId,
            ])->assertSessionHasErrors('parent_id');
        }

        $this->assertDatabaseCount('comments', 2);
    }

    public function test_an_authenticated_reply_uses_the_existing_pending_moderation_and_url_rules(): void
    {
        $parent = Comment::create(['article_id' => $this->article->id, 'author_name' => 'Samir', 'content' => 'Commentaire parent', 'status' => 'approved']);
        $user = User::factory()->create();

        $this->actingAs($user)->post('/discussion/commentaires', [
            'name' => 'Amina', 'content' => 'Réponse sans lien.', 'parent_id' => $parent->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('comments', ['article_id' => $this->article->id, 'parent_id' => $parent->id, 'status' => 'pending']);
        $this->actingAs($user)->post('/discussion/commentaires', [
            'name' => 'Amina', 'content' => 'https://example.test', 'parent_id' => $parent->id,
        ])->assertSessionHasErrors('content');
    }
}
