<?php

namespace App\Console\Commands;

use App\Services\LegacyRestaurantMigrator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

class LegacyMigrateRestaurantsCommand extends Command
{
    protected $signature = 'legacy:migrate-restaurants
        {--dry-run : Inspect only; this is the default behaviour}
        {--apply : Persist to the V2 database after inspection}
        {--limit=10 : Maximum deterministic sample size (never defaults to a full import)}
        {--out=docs/generated/restaurant-migration-sample : Output path without extension}';

    protected $description = 'Migrate a deterministic, limited ListingPro restaurant sample from the read-only legacy database.';

    public function handle(): int
    {
        if ($this->option('dry-run') && $this->option('apply')) { $this->error('Choose either --dry-run or --apply.'); return self::FAILURE; }
        $limit = (int) $this->option('limit');
        if ($limit < 1 || $limit > 10) { $this->error('--limit must be between 1 and 10 for this controlled migration.'); return self::FAILURE; }
        $apply = (bool) $this->option('apply');
        $migrator = app(LegacyRestaurantMigrator::class);
        $selection = $migrator->sample($limit);
        $report = ['generated_at' => now()->toIso8601String(), 'mode' => $apply ? 'apply' : 'dry-run', 'selection' => $selection, 'restaurants' => [], 'summary' => ['inspected' => 0, 'persisted' => 0, 'failed' => 0, 'anomalies' => 0]];

        foreach ($selection as $reason => $id) {
            try {
                $record = $migrator->inspect($id, $reason);
                $report['summary']['inspected']++;
                $report['summary']['anomalies'] += count($record['anomalies']);
                if ($apply) { $migrator->persist($record); $record['result'] = 'persisted'; $report['summary']['persisted']++; }
                else $record['result'] = 'planned';
                $report['restaurants'][] = $record;
            } catch (Throwable $exception) {
                $report['summary']['failed']++;
                $report['restaurants'][] = ['legacy_wp_id' => $id, 'selection_reason' => $reason, 'result' => 'failed', 'anomalies' => ['migration_failure'], 'error' => $exception->getMessage()];
                $this->error("Listing $id failed: {$exception->getMessage()}");
            }
        }
        $out = base_path((string) $this->option('out'));
        File::ensureDirectoryExists(dirname($out));
        File::put($out.'.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        File::put($out.'.md', $this->markdown($report));
        $this->info(($apply ? 'Applied' : 'Dry-run').' report: '.$out.'.json');
        return $report['summary']['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @param array<string, mixed> $report */
    private function markdown(array $report): string
    {
        $lines = ['# Restaurant Migration Sample', '', '- Mode: `'.$report['mode'].'`', '- Selection: `'.json_encode($report['selection']).'`', '- Summary: `'.json_encode($report['summary']).'`', '', '## Records', ''];
        foreach ($report['restaurants'] as $record) $lines[] = '- Legacy `'.($record['source']['legacy_wp_id'] ?? $record['legacy_wp_id']).'`: `'.($record['result'] ?? 'failed').'`; anomalies: `'.implode(', ', $record['anomalies'] ?? []).'`';
        return implode("\n", [...$lines, '']);
    }
}
