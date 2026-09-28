<?php

namespace App\Console\Commands;

use App\Services\Regression\SentinelRegistry;
use Illuminate\Console\Command;

class RegressionSentinelsCommand extends Command
{
    protected $signature = 'regression:sentinels {--refresh-baseline : Deprecated compatibility option}';
    protected $description = 'Deprecated: regression now uses dynamic integrity checks and deployment snapshots.';

    public function handle(SentinelRegistry $registry): int
    {
        $this->warn('Deprecated: use regression:snapshot before deployment and regression:verify --snapshot afterwards.');
        return self::SUCCESS;
    }
}
