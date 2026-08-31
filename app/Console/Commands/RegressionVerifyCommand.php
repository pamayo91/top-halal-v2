<?php

namespace App\Console\Commands;

use App\Services\Regression\SentinelRegistry;
use Illuminate\Console\Command;

class RegressionVerifyCommand extends Command
{
    protected $signature = 'regression:verify {--json : Emit only machine-readable validation data}';
    protected $description = 'Verifies approved database, relation, media and legacy-URL regression sentinels.';

    public function handle(SentinelRegistry $registry): int
    {
        $result = $registry->verify();
        if ($this->option('json')) {
            $this->output->write(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        } else {
            foreach ($result['errors'] as $error) $this->error($error);
            $this->info('Sentinel URLs: '.count($result['urls']).'; media URLs: '.count($result['media_urls']).'.');
        }
        return $result['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
