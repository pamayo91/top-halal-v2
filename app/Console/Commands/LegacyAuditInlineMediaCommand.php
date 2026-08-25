<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Page;
use App\Services\LegacyContentReader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class LegacyAuditInlineMediaCommand extends Command
{
    protected $signature = 'legacy:audit-inline-media {--out=docs/generated/inline-media-debt}';

    protected $description = 'Records inline legacy image references removed from the editorial pilot output.';

    public function handle(LegacyContentReader $reader): int
    {
        $items = [];

        foreach ([['post', Article::class], ['page', Page::class]] as [$type, $model]) {
            foreach ($model::orderBy('legacy_wp_id')->pluck('legacy_wp_id') as $id) {
                $content = (string) $reader->read($type, $id)['post']->post_content;
                preg_match_all('/<img\b[^>]*\bsrc=["\']([^"\']*top-halal\.fr\/wp-conten(?:t|u)[^"\']*)["\'][^>]*>/i', $content, $matches, PREG_OFFSET_CAPTURE);

                foreach ($matches[1] as $position => [$url, $offset]) {
                    $path = (string) parse_url(html_entity_decode($url), PHP_URL_PATH);
                    $attachment = DB::connection('legacy_wp')->table('posts')
                        ->where('post_type', 'attachment')
                        ->where('guid', 'like', '%'.$path)
                        ->value('ID');
                    $before = strip_tags(substr($content, max(0, $offset - 160), 160));
                    $items[] = [
                        'content_type' => $type,
                        'legacy_wp_id' => (int) $id,
                        'position' => $position + 1,
                        'source_url' => $url,
                        'legacy_path' => $path,
                        'legacy_attachment_id' => $attachment ? (int) $attachment : null,
                        'context_excerpt' => trim(preg_replace('/\s+/', ' ', $before)),
                        'action' => 'not_migrated_physical_media_pending_reconciliation',
                    ];
                }
            }
        }

        $report = [
            'scope' => 'currently migrated editorial pilot only',
            'physical_media_copied' => false,
            'items' => $items,
        ];
        $base = base_path($this->option('out'));
        File::put($base.'.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $lines = ['# Inline Media Debt', '', 'Legacy inline images removed from V2 HTML remain a future media-reconciliation backlog. No physical media was copied.', '', '| Type | Legacy ID | Position | Attachment | Source path | Context |', '|---|---:|---:|---:|---|---|'];
        foreach ($items as $item) {
            $lines[] = sprintf('| %s | %d | %d | %s | `%s` | %s |', $item['content_type'], $item['legacy_wp_id'], $item['position'], $item['legacy_attachment_id'] ?? 'unresolved', $item['legacy_path'], str_replace('|', '\\|', $item['context_excerpt'] ?: 'none'));
        }
        File::put($base.'.md', implode("\n", $lines)."\n");
        $this->info(count($items).' inline legacy image references recorded.');

        return self::SUCCESS;
    }
}
