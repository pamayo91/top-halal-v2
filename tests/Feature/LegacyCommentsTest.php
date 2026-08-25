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
        $this->legacy()->listen(function ($query) use (&$writes): void { if (preg_match('/^\s*(insert|update|delete|alter|drop)/i', $query->sql)) $writes[] = $query->sql; });
        $this->artisan('legacy:migrate-comments', ['--ids' => '1,2', '--apply' => true])->assertExitCode(0);
        $this->artisan('legacy:migrate-comments', ['--ids' => '1,2', '--apply' => true])->assertExitCode(0);

        $this->assertSame([], $writes);
        $this->assertSame(2, Comment::count());
        $this->assertSame('Bonjour été', Comment::where('legacy_wp_comment_id', 1)->value('content'));
        $this->assertSame(Comment::where('legacy_wp_comment_id', 1)->value('id'), Comment::where('legacy_wp_comment_id', 2)->value('parent_id'));
    }

    public function test_a_missing_parent_is_reported_without_inventing_one(): void
    {
        $this->legacy()->table('comments')->insert($this->row(3, 99, 'Orphelin'));
        $this->artisan('legacy:migrate-comments', ['--ids' => '3', '--apply' => true])->assertExitCode(0);
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

    public function test_migration_rolls_back_on_target_constraint_failure(): void
    {
        $this->legacy()->table('comments')->insert($this->row(4, 0, 'Collision'));
        Comment::create(['legacy_wp_comment_id' => 99, 'article_id' => 1, 'author_name' => 'x', 'content' => 'x', 'status' => 'approved']);
        DB::table('articles')->where('id', 1)->update(['slug' => 'still-valid']);
        $this->artisan('legacy:migrate-comments', ['--ids' => '4', '--apply' => true])->assertExitCode(0);
        $this->assertSame(1, Comment::where('legacy_wp_comment_id', 4)->count());
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
