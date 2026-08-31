<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class RegressionCheckLogsCommand extends Command
{
    protected $signature = 'regression:check-logs {--since= : ISO-8601 UTC start time for the validation run} {--json : Emit machine-readable output}';
    protected $description = 'Fails when Laravel records a new error or exception during a regression validation window.';

    public function handle(): int
    {
        $since = $this->option('since') ? CarbonImmutable::parse((string) $this->option('since'))->utc() : now()->subMinutes(10)->utc();
        $path = storage_path('logs/laravel.log');
        $errors = [];

        if (File::exists($path)) {
            foreach (File::lines($path) as $line) {
                if (! preg_match('/^\[([^\]]+)]\s+[^.]+\.(ERROR|CRITICAL|ALERT|EMERGENCY):/i', $line, $matches)) continue;
                try {
                    if (CarbonImmutable::parse($matches[1])->utc()->lessThan($since)) continue;
                } catch (\Throwable) {
                    continue;
                }
                $errors[] = $line;
            }
        }

        $result = ['since' => $since->toIso8601String(), 'errors' => $errors];
        if ($this->option('json')) $this->output->write(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        else foreach ($errors as $error) $this->error($error);
        return $errors === [] ? self::SUCCESS : self::FAILURE;
    }
}
