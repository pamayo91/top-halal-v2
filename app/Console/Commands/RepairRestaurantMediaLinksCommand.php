<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB, File};

class RepairRestaurantMediaLinksCommand extends Command
{
    protected $signature = 'data:repair-restaurant-media-links
        {--apply : Write only verified V2 media_asset_id links}
        {--out=docs/generated/restaurant-media-link-repair : Report path without extension}';

    protected $description = 'Repairs missing restaurant-to-V2-media links without reading legacy data.';

    public function handle(): int
    {
        $before = $this->audit();
        $result = ['candidates' => 0, 'repaired' => 0, 'conflicts' => 0, 'samples' => []];

        $this->candidates()->orderBy('restaurant_media.id')->chunkById(200, function ($rows) use (&$result): void {
            foreach ($rows as $row) {
                $result['candidates']++;

                $hasConflict = DB::table('restaurant_media')
                    ->where('restaurant_id', $row->restaurant_id)
                    ->where('media_asset_id', $row->media_asset_id)
                    ->where('id', '!=', $row->id)
                    ->exists();

                if ($hasConflict) {
                    $result['conflicts']++;
                    $this->sample($result['samples'], $row, 'conflict');

                    continue;
                }

                if ($this->option('apply')) {
                    $updated = DB::table('restaurant_media')
                        ->where('id', $row->id)
                        ->whereNull('media_asset_id')
                        ->update(['media_asset_id' => $row->media_asset_id, 'updated_at' => now()]);

                    if ($updated === 1) {
                        $result['repaired']++;
                        $this->sample($result['samples'], $row, 'repaired');
                    }
                } else {
                    $this->sample($result['samples'], $row, 'would_repair');
                }
            }
        }, 'restaurant_media.id', 'id');

        $after = $this->audit();
        $report = [
            'generated_at' => now()->toIso8601String(),
            'mode' => $this->option('apply') ? 'apply' : 'dry-run',
            'source' => 'V2 database only; no legacy connection or storage is read.',
            'before' => $before,
            'result' => $result,
            'after' => $after,
        ];
        $this->writeReport($report);

        $this->info(sprintf(
            'Candidates: %d; repaired: %d; conflicts: %d.',
            $result['candidates'],
            $result['repaired'],
            $result['conflicts'],
        ));

        return self::SUCCESS;
    }

    private function candidates()
    {
        return DB::table('restaurant_media')
            ->join('media_assets', 'media_assets.legacy_attachment_id', '=', 'restaurant_media.legacy_attachment_id')
            ->whereNull('restaurant_media.media_asset_id')
            ->whereNotNull('restaurant_media.legacy_attachment_id')
            ->select([
                'restaurant_media.id',
                'restaurant_media.restaurant_id',
                'media_assets.id as media_asset_id',
            ]);
    }

    /** @return array<string, int> */
    private function audit(): array
    {
        return [
            'restaurant_media_total' => DB::table('restaurant_media')->count(),
            'linked' => DB::table('restaurant_media')->whereNotNull('media_asset_id')->count(),
            'unlinked_with_matching_v2_asset' => $this->candidates()->count(),
            'unlinked_without_matching_v2_asset' => DB::table('restaurant_media')
                ->leftJoin('media_assets', 'media_assets.legacy_attachment_id', '=', 'restaurant_media.legacy_attachment_id')
                ->whereNull('restaurant_media.media_asset_id')
                ->whereNull('media_assets.id')
                ->count(),
        ];
    }

    /** @param array<int, array<string, int|string>> $samples */
    private function sample(array &$samples, object $row, string $outcome): void
    {
        if (count($samples) < 20) {
            $samples[] = [
                'restaurant_media_id' => $row->id,
                'restaurant_id' => $row->restaurant_id,
                'media_asset_id' => $row->media_asset_id,
                'outcome' => $outcome,
            ];
        }
    }

    /** @param array<string, mixed> $report */
    private function writeReport(array $report): void
    {
        $base = base_path((string) $this->option('out'));
        File::ensureDirectoryExists(dirname($base));
        File::put($base.'.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        $lines = [
            '# Réparation des liens restaurant / médias V2',
            '',
            'Généré le : `'.$report['generated_at'].'`',
            'Mode : `'.$report['mode'].'`',
            '',
            'Cette opération consulte exclusivement les tables V2 `restaurant_media` et `media_assets`.',
            '',
            '## Résultat',
            '- Liens candidats : '.$report['result']['candidates'].'.',
            '- Liens réparés : '.$report['result']['repaired'].'.',
            '- Conflits conservés pour revue : '.$report['result']['conflicts'].'.',
            '- Liens V2 valides après opération : '.$report['after']['linked'].'.',
            '- Relations sans asset V2 correspondant : '.$report['after']['unlinked_without_matching_v2_asset'].'.',
        ];
        File::put($base.'.md', implode("\n", $lines)."\n");
    }
}
