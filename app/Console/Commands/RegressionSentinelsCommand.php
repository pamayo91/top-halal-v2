<?php

namespace App\Console\Commands;

use App\Models\RegressionSentinel;
use App\Services\Regression\SentinelRegistry;
use Illuminate\Console\Command;

class RegressionSentinelsCommand extends Command
{
    protected $signature = 'regression:sentinels {--refresh-baseline : Deliberately replace the approved baseline with the current V2 state}';
    protected $description = 'Discovers and records representative V2 data sentinels without using legacy data.';

    public function handle(SentinelRegistry $registry): int
    {
        $registry->persist((bool) $this->option('refresh-baseline'));
        $counts = RegressionSentinel::where('key', 'database.counts')->first();
        if (! $counts || $this->option('refresh-baseline')) {
            RegressionSentinel::updateOrCreate(['key' => 'database.counts'], [
                'subject_type' => 'database', 'subject_id' => null, 'route_path' => null,
                'baseline' => ['counts' => $registry->counts()],
            ]);
        }
        $this->info(RegressionSentinel::count().' regression sentinels registered.');
        return self::SUCCESS;
    }
}
