<?php

namespace App\Console\Commands;

use App\Services\{LegacyReviewMigrator, LegacyReviewReader};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class LegacyMigrateReviewsCommand extends Command
{
    protected $signature = 'legacy:migrate-reviews {--ids=} {--restaurant-ids=} {--dry-run} {--apply} {--out=docs/generated/reviews-migration-sample}';
    protected $description = 'Migrates only reviewed ListingPro review IDs from the read-only legacy source.';
    public function handle(LegacyReviewReader $reader, LegacyReviewMigrator $migrator): int
    {
        $ids = $this->ids('ids'); $restaurantIds = $this->ids('restaurant-ids');
        if (($ids === [] && $restaurantIds === []) || ($this->option('dry-run') && $this->option('apply'))) { $this->error('Provide --ids or --restaurant-ids, and at most one of --dry-run/--apply.'); return self::FAILURE; }
        $report = ['mode' => $this->option('apply') ? 'apply' : 'dry-run', 'items' => [], 'anomalies' => []];
        foreach ($reader->findMany($ids, $restaurantIds) as $post) {
            $record = $migrator->inspect($post); $item = ['source' => $record['source'], 'destination_restaurant_id' => $record['target']['restaurant_id'], 'rating' => $record['target']['rating'], 'title_present' => $record['target']['title'] !== null, 'content_length' => mb_strlen($record['target']['content']), 'status' => $record['target']['status'], 'transformation' => 'legacy_html_to_text_and_escaped_output', 'anomalies' => $record['anomalies']];
            if ($this->option('apply') && $record['anomalies'] === []) { $migrator->persist($record); $item['result'] = 'persisted'; } else $item['result'] = $record['anomalies'] === [] ? 'planned' : 'skipped';
            $report['items'][] = $item; foreach ($record['anomalies'] as $anomaly) $report['anomalies'][] = ['legacy_wp_review_id' => $record['source']['legacy_wp_review_id'], 'code' => $anomaly];
        }
        $base = base_path($this->option('out')); File::ensureDirectoryExists(dirname($base)); File::put($base.'.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); File::put($base.'.md', "# Reviews Migration Pilot\n\n```json\n".json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n```\n");
        $this->info(count($report['items']).' reviews processed.'); return self::SUCCESS;
    }
    private function ids(string $option): array { return array_values(array_unique(array_filter(array_map('intval', explode(',', (string) $this->option($option)))))); }
}
