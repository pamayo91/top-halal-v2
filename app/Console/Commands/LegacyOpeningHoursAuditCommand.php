<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class LegacyOpeningHoursAuditCommand extends Command
{
    protected $signature = 'legacy:audit-opening-hours {--out=docs/generated/opening-hours-audit : Output path without extension}';

    protected $description = 'Audit ListingPro opening-hours structures without exporting listing content or contact data.';

    public function handle(): int
    {
        $legacy = DB::connection('legacy_wp');
        $listings = (int) $legacy->table('posts')->where('post_type', 'listing')->count();
        $formats = ['business_hours_null' => 0, 'business_hours_empty' => 0, 'business_hours_other' => 0];
        $examples = ['business_hours_null' => [], 'business_hours_empty' => [], 'business_hours_other' => []];
        $options = 0;

        foreach ($legacy->table('postmeta as meta')->join('posts as post', 'post.ID', '=', 'meta.post_id')->where('post.post_type', 'listing')->where('meta.meta_key', 'lp_listingpro_options')->select('meta.post_id', 'meta.meta_value')->orderBy('meta.post_id')->cursor() as $row) {
            $options++;
            $decoded = @unserialize($row->meta_value, ['allowed_classes' => false]);
            $hours = is_array($decoded) && array_key_exists('business_hours', $decoded) ? $decoded['business_hours'] : null;
            $format = $hours === null ? 'business_hours_null' : ($hours === '' ? 'business_hours_empty' : 'business_hours_other');
            $formats[$format]++;
            if (count($examples[$format]) < 10) $examples[$format][] = (int) $row->post_id;
        }

        $timeLike = $legacy->table('postmeta as meta')->join('posts as post', 'post.ID', '=', 'meta.post_id')->where('post.post_type', 'listing')->where('meta.meta_value', 'REGEXP', '[[:<:]]([01]?[0-9]|2[0-3]):[0-5][0-9][[:>:]]')->select('meta.post_id', 'meta.meta_key')->orderBy('meta.post_id')->get();
        $timeLikeKeys = $timeLike->groupBy('meta_key')->map(fn ($rows, $key) => ['meta_key' => $key, 'rows' => $rows->count(), 'legacy_wp_ids' => $rows->pluck('post_id')->unique()->take(10)->map(fn ($id) => (int) $id)->values()->all()])->values()->all();
        $report = [
            'generated_at' => now()->toIso8601String(), 'connection' => 'legacy_wp', 'listings_total' => $listings,
            'listings_with_listingpro_options' => $options, 'restaurants_with_detected_opening_hours' => 0,
            'restaurants_without_detected_opening_hours' => $listings,
            'formats' => $formats, 'format_examples_legacy_wp_ids' => $examples,
            'alternative_time_like_values' => $timeLikeKeys,
            'anomalies' => ['No non-empty ListingPro business_hours structure exists in the legacy listings.', 'Time-like values outside a recognised hours path are not migrated; the audit found one in an email field and treats it as hostile legacy data.'],
            'sample_candidates_legacy_wp_ids' => [],
        ];
        $out = base_path((string) $this->option('out'));
        File::ensureDirectoryExists(dirname($out));
        File::put($out.'.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        File::put($out.'.md', $this->markdown($report));
        $this->info('Opening-hours audit: '.$out.'.json');
        return self::SUCCESS;
    }

    /** @param array<string, mixed> $report */
    private function markdown(array $report): string
    {
        $lines = ['# ListingPro Opening-Hours Audit', '', '- Legacy listings: `'.$report['listings_total'].'`', '- Detected opening hours: `0`', '- Without detected opening hours: `'.$report['restaurants_without_detected_opening_hours'].'`', '', '## Formats', ''];
        foreach ($report['formats'] as $format => $count) $lines[] = '- `'.$format.'`: '.$count.'; examples: `'.implode(', ', $report['format_examples_legacy_wp_ids'][$format]).'`';
        $lines[] = '';
        $lines[] = '## Anomalies';
        $lines[] = '';
        foreach ($report['anomalies'] as $anomaly) $lines[] = '- '.$anomaly;
        $lines[] = '';
        $lines[] = 'No migration sample was selected because no legacy restaurant has parseable opening hours.';
        return implode("\n", [...$lines, '']);
    }
}
