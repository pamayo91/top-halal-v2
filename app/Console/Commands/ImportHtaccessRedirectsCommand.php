<?php

namespace App\Console\Commands;

use App\Models\RedirectRule;
use App\Services\RedirectResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ImportHtaccessRedirectsCommand extends Command
{
    protected $signature = 'redirects:import-htaccess {--file=docs/redirect-inventory.json} {--dry-run}';
    protected $description = 'Imports application redirect rules from the reviewed htaccess inventory.';
    public function handle(): int
    {
        $inventory = json_decode(File::get(base_path($this->option('file'))), true, 512, JSON_THROW_ON_ERROR);
        $report = ['imported' => 0, 'infrastructure' => [], 'duplicates' => [], 'review' => []]; $seen = [];
        foreach ($inventory['records'] as $record) {
            if ($record['infrastructure']) { $report['infrastructure'][] = $record['line']; continue; }
            [$source, $query, $review] = $this->compileConditions($record);
            if ($review) { $report['review'][] = $record['line']; continue; }
            $key = implode('|', [$record['kind'], $source, $query]);
            if (isset($seen[$key])) { $report['duplicates'][] = $record['line']; continue; }
            $seen[$key] = true;
            $destination = $record['destination_path'];
            if ($record['kind'] === 'exact' && str_contains($destination, '$')) { $report['review'][] = $record['line']; continue; }
            if (! $this->option('dry-run')) RedirectRule::updateOrCreate(
                ['source_path' => $source, 'match_type' => $record['kind'] === 'exact' ? 'exact' : 'regex', 'query_pattern' => $query],
                ['destination' => $destination, 'status_code' => 301, 'preserve_query' => ! $record['drops_query'], 'priority' => $record['line'], 'is_active' => true, 'origin' => 'htaccess', 'source_rule' => $record['raw']]
            );
            $report['imported']++;
        }
        if (! $this->option('dry-run')) app(RedirectResolver::class)->clearCache();
        File::put(base_path('docs/generated/redirect-import-report.json'), json_encode($report, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
        $this->info("{$report['imported']} rules ready; ".count($report['infrastructure']).' kept in Apache.'); return 0;
    }

    /** Converts only deterministic REQUEST_URI / QUERY_STRING conditions; every other condition is surfaced for review. */
    private function compileConditions(array $record): array
    {
        $source = $record['source']; $query = null; $negativePaths = [];
        foreach ($record['conditions'] as $condition) {
            $expr = preg_replace('/\s+\[or\]$/i', '', $condition['expr']);
            if (preg_match('/^%\{QUERY_STRING\}\s+(.+)$/', $expr, $match)) { $query = $match[1]; continue; }
            if (! preg_match('/^%\{REQUEST_URI\}\s+(!?)(.+)$/', $expr, $match)) return [$source, $query, true];
            $pathPattern = ltrim($match[2], '/');
            if ($match[1] === '!') { $negativePaths[] = $pathPattern; continue; }
            if ($source === '^.*$' || $source === '^(.*)$') $source = '^'.$pathPattern;
            else return [$source, $query, true];
        }
        if ($negativePaths) {
            if (! str_starts_with($source, '^')) return [$source, $query, true];
            $source = '^'.collect($negativePaths)->map(fn ($pattern) => '(?!'.$pattern.')')->implode('').substr($source, 1);
        }
        return [$source, $query, false];
    }
}
