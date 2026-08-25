<?php

namespace App\Console\Commands;

use App\Models\{Article, Comment, Page};
use App\Services\LegacyCommentReader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB, File};

class LegacyMigrateCommentsCommand extends Command
{
    protected $signature = 'legacy:migrate-comments
        {--ids= : Comma-separated legacy comment IDs}
        {--post-ids= : Comma-separated legacy WordPress post IDs}
        {--dry-run : Report only}
        {--apply : Explicitly write to V2}
        {--out=docs/generated/comments-migration-sample}';

    protected $description = 'Migrates reviewed WordPress editorial comments only.';

    public function handle(LegacyCommentReader $reader): int
    {
        $ids = $this->ids('ids');
        $postIds = $this->ids('post-ids');
        if (($ids === [] && $postIds === []) || ($this->option('dry-run') && $this->option('apply'))) {
            $this->error('Provide --ids or --post-ids, and at most one of --dry-run/--apply.');
            return self::FAILURE;
        }

        $legacy = $reader->findMany($ids, $postIds);
        $report = ['mode' => $this->option('apply') ? 'apply' : 'dry-run', 'items' => [], 'anomalies' => []];
        foreach ($legacy as $row) {
            $target = $this->targetFor((int) $row->comment_post_ID);
            $item = $this->reportItem($row, $target);
            $report['items'][] = $item;
            if ($target === null) $report['anomalies'][] = ['legacy_wp_comment_id' => (int) $row->comment_ID, 'code' => 'unmigrated_or_unsupported_post'];
        }

        if ($this->option('apply')) {
            DB::transaction(function () use ($legacy, &$report): void {
                $mapped = [];
                foreach ($legacy as $row) {
                    $target = $this->targetFor((int) $row->comment_post_ID);
                    if ($target === null) continue;
                    $comment = Comment::updateOrCreate(['legacy_wp_comment_id' => $row->comment_ID], [
                        'legacy_wp_post_id' => $row->comment_post_ID,
                        'article_id' => $target['type'] === 'post' ? $target['id'] : null,
                        'page_id' => $target['type'] === 'page' ? $target['id'] : null,
                        'legacy_user_id' => $row->user_id ?: null,
                        'author_name' => trim(strip_tags((string) $row->comment_author)) ?: 'Anonyme',
                        'author_email' => $row->comment_author_email ?: null,
                        'content' => trim(html_entity_decode(strip_tags((string) $row->comment_content), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
                        'status' => $row->comment_approved === '1' ? 'approved' : 'pending',
                        'approved_at' => $row->comment_approved === '1' ? $row->comment_date_gmt : null,
                        'created_at' => $row->comment_date_gmt,
                        'updated_at' => $row->comment_date_gmt,
                    ]);
                    $mapped[(int) $row->comment_ID] = $comment->id;
                }
                foreach ($legacy as $row) {
                    if (!$row->comment_parent || !isset($mapped[(int) $row->comment_ID])) continue;
                    if (!isset($mapped[(int) $row->comment_parent])) {
                        $report['anomalies'][] = ['legacy_wp_comment_id' => (int) $row->comment_ID, 'code' => 'parent_not_in_selected_scope'];
                        continue;
                    }
                    Comment::whereKey($mapped[(int) $row->comment_ID])->update(['parent_id' => $mapped[(int) $row->comment_parent]);
                }
            });
        }

        $base = base_path($this->option('out'));
        File::put($base.'.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        File::put($base.'.md', "# Comments migration sample\n\n```json\n".json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n```\n");
        $this->info(count($report['items']).' reviewed comments processed.');
        return self::SUCCESS;
    }

    private function ids(string $option): array
    {
        return array_values(array_unique(array_filter(array_map('intval', explode(',', (string) $this->option($option))))));
    }

    private function targetFor(int $legacyPostId): ?array
    {
        if ($article = Article::where('legacy_wp_id', $legacyPostId)->first()) return ['type' => 'post', 'id' => $article->id];
        if ($page = Page::where('legacy_wp_id', $legacyPostId)->first()) return ['type' => 'page', 'id' => $page->id];
        return null;
    }

    private function reportItem(object $row, ?array $target): array
    {
        return [
            'legacy_wp_comment_id' => (int) $row->comment_ID,
            'legacy_wp_post_id' => (int) $row->comment_post_ID,
            'destination' => $target ? $target['type'].':'.$target['id'] : null,
            'status' => $row->comment_approved === '1' ? 'approved' : 'pending',
            'parent_legacy_wp_comment_id' => (int) $row->comment_parent ?: null,
            'legacy_user_id' => (int) $row->user_id ?: null,
            'guest' => !(bool) $row->user_id,
            'author_url_present' => $row->comment_author_url !== '',
            'content_has_url' => (bool) preg_match('/https?:\/\/|www\./i', (string) $row->comment_content),
            'content_has_html' => (bool) preg_match('/<[^>]+>/', (string) $row->comment_content),
            'content_length' => mb_strlen(strip_tags((string) $row->comment_content)),
            'legacy_date_gmt' => $row->comment_date_gmt,
        ];
    }
}
