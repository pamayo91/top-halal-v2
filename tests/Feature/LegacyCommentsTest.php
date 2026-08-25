<?php

namespace Tests\Feature;

use App\Models\{Article, Comment};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class LegacyCommentsTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.connections.legacy_wp', [...config('database.connections.sqlite'), 'prefix' => '']);
        DB::purge('legacy_wp');
        $schema = Schema::connection('legacy_wp');
        $schema->create('comments', function (Blueprint $table): void {
            $table->unsignedBigInteger('comment_ID')->primary(); $table->unsignedBigInteger('comment_post_ID'); $table->string('comment_author'); $table->string('comment_author_email'); $table->string('comment_author_url'); $table->string('comment_date_gmt'); $table->text('comment_content'); $table->string('comment_approved'); $table->string('comment_type'); $table->unsignedBigInteger('comment_parent')->default(0); $table->unsignedBigInteger('user_id')->default(0);
        });
        $schema->create('commentmeta', function (Blueprint $table): void { $table->id(); $table->unsignedBigInteger('comment_id'); $table->string('meta_key'); $table->text('meta_value'); });
        Article::create(['legacy_wp_id' => 27, 'original_title' => 'Été', 'title' => 'Été', 'slug' => 'ete', 'status' => 'published', 'legacy_url' => '/ete/']);
    }

    public function test_it_migrates_a_thread_idempotently_and_never_writes_legacy(): void
    {
        $this->legacy()->table('comments')->insert([$this->row(1, 0, 'Bonjour <strong>été</strong>'), $this->row(2, 1, 'Réponse https://top-halal.fr')]);
        $writes = [];
        $this->legacy()->listen(function ($query) use (&$writes): void { if ($query->connectionName === 'legacy_wp' && preg_match('/^\s*(insert|update|delete|alter|drop)/i', $query->sql)) $writes[] = $query->sql; });
        $this->artisan('legacy:migrate-comments', ['--ids' => '1,2', '--apply' => true, '--out' => 'storage/framework/testing/comments-migration'])->assertExitCode(0);
        $this->artisan('legacy:migrate-comments', ['--ids' => '1,2', '--apply' => true, '--out' => 'storage/framework/testing/comments-migration'])->assertExitCode(0);

        $this->assertSame([], $writes);
        $this->assertSame(2, Comment::count());
        $this->assertSame('Bonjour été', Comment::where('legacy_wp_comment_id', 1)->value('content'));
        $this->assertSame(Comment::where('legacy_wp_comment_id', 1)->value('id'), Comment::where('legacy_wp_comment_id', 2)->value('parent_id'));
    }

    public function test_a_missing_parent_is_reported_without_inventing_one(): void
    {
        $this->legacy()->table('comments')->insert($this->row(3, 99, 'Orphelin'));
        $this->artisan('legacy:migrate-comments', ['--ids' => '3', '--apply' => true, '--out' => 'storage/framework/testing/comments-migration'])->assertExitCode(0);
        $this->assertNull(Comment::where('legacy_wp_comment_id', 3)->value('parent_id'));
    }

    public function test_new_comments_are_pending_escape_html_and_reject_urls(): void
    {
        $this->post('/_preview/post/27/comments', ['name' => 'Élodie', 'email' => 'elodie@example.test', 'content' => '<img src=x onerror=alert(1)> Bonjour'])->assertRedirect();
        $comment = Comment::firstOrFail();
        $this->assertSame('pending', $comment->status);
        $this->assertSame('Bonjour', $comment->content);
        $this->post('/_preview/post/27/comments', ['name' => 'Élodie', 'email' => 'elodie@example.test', 'content' => 'Lire https://example.test'])->assertSessionHasErrors('content');
    }

    public function test_dry_run_never_writes_the_target_database(): void
    {
        $this->legacy()->table('comments')->insert($this->row(4, 0, 'À relire'));
        $this->artisan('legacy:migrate-comments', ['--ids' => '4', '--dry-run' => true, '--out' => 'storage/framework/testing/comments-migration'])->assertExitCode(0);
        $this->assertSame(0, Comment::count());
    }

    public function test_only_approved_comments_are_rendered_as_safe_text(): void
    {
        Comment::create(['article_id' => 1, 'author_name' => 'Luc', 'content' => '<script>alert(1)</script> Bonjour', 'status' => 'approved']);
        Comment::create(['article_id' => 1, 'author_name' => 'Inès', 'content' => 'En attente', 'status' => 'pending']);
        $this->get('/_preview/post/27')
            ->assertOk()
            ->assertSee('Bonjour')
            ->assertDontSee('En attente')
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_technical_moderation_approves_and_deletes_comments(): void
    {
        $comment = Comment::create(['article_id' => 1, 'author_name' => 'Luc', 'content' => 'À vérifier', 'status' => 'pending']);
        $this->artisan('comments:moderate', ['id' => $comment->id, '--status' => 'approved'])->assertExitCode(0);
        $this->assertSame('approved', $comment->fresh()->status);
        $this->artisan('comments:moderate', ['id' => $comment->id, '--delete' => true])->assertExitCode(0);
        $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
    }

    private function legacy()
    {
        return DB::connection('legacy_wp');
    }

    private function row(int $id, int $parent, string $content): array
    {
        return ['comment_ID' => $id, 'comment_post_ID' => 27, 'comment_author' => 'Élodie', 'comment_author_email' => 'elodie@example.test', 'comment_author_url' => '', 'comment_date_gmt' => '2020-01-01 12:00:00', 'comment_content' => $content, 'comment_approved' => '1', 'comment_type' => 'comment', 'comment_parent' => $parent, 'user_id' => 0];
    }
}
