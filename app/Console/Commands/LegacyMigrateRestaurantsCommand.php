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
        {--ids= : Comma-separated explicit legacy restaurant IDs for a reviewed sample}
        {--out=docs/generated/restaurant-migration-sample : Output path without extension}';

    protected $description = 'Migrate a deterministic, limited ListingPro restaurant sample from the read-only legacy database.';

    public function handle(): int
    {
        if ($this->option('dry-run') && $this->option('apply')) { $this->error('Choose either --dry-run or --apply.'); return self::FAILURE; }
        $limit = (int) $this->option('limit');
        if ($limit < 1 || $limit > 10) { $this->error('--limit must be between 1 and 10 for this controlled migration.'); return self::FAILURE; }
        $apply = (bool) $this->option('apply');
        $migrator = app(LegacyRestaurantMigrator::class);
        $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', (string) $this->option('ids'))))));
        if ($ids !== [] && count($ids) > $limit) { $this->error('--ids exceeds --limit.'); return self::FAILURE; }
        $selection = $ids === [] ? $migrator->sample($limit) : collect($ids)->mapWithKeys(fn (int $id) => [$id => ['explicit_reviewed_sample']])->all();
        $report = ['generated_at' => now()->toIso8601String(), 'mode' => $apply ? 'apply' : 'dry-run', 'selection' => $selection, 'restaurants' => [], 'summary' => ['inspected' => 0, 'persisted' => 0, 'failed' => 0, 'anomalies' => 0]];

        foreach ($selection as $id => $reasons) {
            try {
                $record = $migrator->inspect((int) $id, $reasons);
                $report['summary']['inspected']++;
                $report['summary']['anomalies'] += count($record['anomalies']);
                if ($apply) { $migrator->persist($record); $record['result'] = 'persisted'; $report['summary']['persisted']++; }
                else $record['result'] = 'planned';
                $report['restaurants'][] = $this->safeRecord($record);
            } catch (Throwable $exception) {
                $report['summary']['failed']++;
                $report['restaurants'][] = ['legacy_wp_id' => (int) $id, 'selection_reasons' => $reasons, 'result' => 'failed', 'anomalies' => ['migration_failure'], 'error' => $exception->getMessage()];
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

    /** @param array<string, mixed> $record @return array<string, mixed> */
    private function safeRecord(array $record): array
    {
        $restaurant = $record['target']['restaurant'];
        return [
            'source' => $record['source'],
            'transformed' => [
                'restaurant' => [
                    'legacy_wp_id' => $restaurant['legacy_wp_id'], 'name' => $restaurant['name'], 'slug' => $restaurant['slug'],
                    'status' => $restaurant['status'], 'is_claimed' => $restaurant['is_claimed'],
                    'has_address' => $restaurant['address'] !== null, 'has_postal_code' => $restaurant['postal_code'] !== null,
                    'has_city_name' => $restaurant['city_name'] !== null, 'has_phone' => $restaurant['phone'] !== null,
                    'has_contact_email' => $restaurant['contact_email'] !== null, 'latitude' => $restaurant['latitude'], 'longitude' => $restaurant['longitude'],
                ],
                'terms' => $record['target']['terms'],
                'hours' => collect($record['target']['hours'])->map(fn (array $hour) => array_diff_key($hour, ['legacy_value' => true]))->all(),
                'media' => collect($record['target']['media'])->map(fn (array $media) => [
                    'legacy_attachment_id' => $media['legacy_attachment_id'], 'sort_order' => $media['sort_order'], 'status' => $media['status'],
                ])->all(),
            ],
            'anomalies' => $record['anomalies'], 'destination' => $record['destination'], 'result' => $record['result'],
        ];
    }
}
