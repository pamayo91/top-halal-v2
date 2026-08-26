<?php

namespace App\Console\Commands;

use App\Models\RedirectRule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AuditRedirectRulesCommand extends Command
{
    protected $signature = 'redirects:audit {--out=docs/generated/redirect-rule-audit.json}';
    protected $description = 'Reports duplicate, conflicting, looping and chained application redirects.';
    public function handle(): int
    {
        $rules = RedirectRule::orderBy('id')->get(); $bySource = $rules->groupBy(fn ($rule) => $rule->match_type.'|'.$rule->source_path.'|'.($rule->query_pattern ?? ''));
        $duplicates = $bySource->filter(fn ($group) => $group->count() > 1)->map(fn ($group) => $group->pluck('id')->all())->values()->all();
        $conflicts = $bySource->filter(fn ($group) => $group->pluck('destination')->unique()->count() > 1)->map(fn ($group) => $group->pluck('id')->all())->values()->all();
        $exact = $rules->where('match_type', 'exact')->keyBy('source_path'); $loops = []; $chains = [];
        foreach ($exact as $source => $rule) {
            $seen = [$source]; $destination = '/'.ltrim(parse_url($rule->destination, PHP_URL_PATH) ?: '/', '/');
            while (isset($exact[$destination])) {
                if (in_array($destination, $seen, true)) { $loops[] = $seen; break; }
                $seen[] = $destination; $destination = '/'.ltrim(parse_url($exact[$destination]->destination, PHP_URL_PATH) ?: '/', '/');
            }
            if (count($seen) > 1 && ! in_array($seen, $loops, true)) $chains[] = $seen;
        }
        $report = compact('duplicates', 'conflicts', 'loops', 'chains') + ['checked_at' => now()->toAtomString(), 'rule_count' => $rules->count()];
        File::put(base_path($this->option('out')), json_encode($report, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
        $this->info("{$rules->count()} rules audited; ".count($duplicates).' duplicates, '.count($conflicts).' conflicts, '.count($loops).' loops, '.count($chains).' chains.');
        return ($conflicts || $loops) ? self::FAILURE : self::SUCCESS;
    }
}
