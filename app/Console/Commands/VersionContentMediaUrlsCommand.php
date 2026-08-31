<?php

namespace App\Console\Commands;

use App\Models\{Article, Page};
use App\Services\ContentMediaUrlVersioner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class VersionContentMediaUrlsCommand extends Command
{
    protected $signature = 'media:version-content-urls {--dry-run} {--apply} {--out=docs/generated/media-url-versioning}';

    protected $description = 'Replace legacy numeric V2 media URLs in editorial HTML with checksum-versioned URLs.';

    public function handle(ContentMediaUrlVersioner $versioner): int
    {
        if ($this->option('dry-run') && $this->option('apply')) {
            $this->error('Choose either --dry-run or --apply.');
            return self::FAILURE;
        }

        $report = ['mode' => $this->option('apply') ? 'apply' : 'dry-run', 'contents' => [], 'updated' => 0, 'replaced' => 0, 'unresolved_asset_ids' => []];
        foreach ([['article', Article::class], ['page', Page::class]] as [$type, $model]) {
            foreach ($model::query()->where('content_html', 'like', '%/media/%')->orderBy('id')->cursor() as $content) {
                $result = $versioner->rewrite((string) $content->content_html);
                if ($result['replaced'] === 0 && $result['unresolved_asset_ids'] === []) continue;
                $changed = $result['html'] !== $content->content_html;
                if ($this->option('apply') && $changed) $content->update(['content_html' => $result['html']]);
                $report['contents'][] = ['type' => $type, 'id' => $content->id, 'replaced' => $result['replaced'], 'unresolved_asset_ids' => $result['unresolved_asset_ids']];
                $report['updated'] += $changed ? 1 : 0;
                $report['replaced'] += $result['replaced'];
                $report['unresolved_asset_ids'] = array_values(array_unique(array_merge($report['unresolved_asset_ids'], $result['unresolved_asset_ids'])));
            }
        }

        $path = base_path($this->option('out'));
        File::put($path.'.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        File::put($path.'.md', "# Versioning des URL média V2\n\n".json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->info("{$report['updated']} contenus mis à jour, {$report['replaced']} URL remplacées.");

        return $report['unresolved_asset_ids'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
