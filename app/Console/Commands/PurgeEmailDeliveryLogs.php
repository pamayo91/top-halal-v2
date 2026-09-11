<?php

namespace App\Console\Commands;

use App\Services\TransactionalMailService;
use Illuminate\Console\Command;

class PurgeEmailDeliveryLogs extends Command
{
    protected $signature = 'emails:purge-delivery-logs {--days=60 : Âge minimal en jours} {--dry-run : Compte sans supprimer}';
    protected $description = 'Purge les historiques annulés ou expirés après leur durée de conservation.';

    public function handle(TransactionalMailService $mail): int
    {
        $days = max(1, (int) $this->option('days'));
        $dryRun = (bool) $this->option('dry-run');
        $count = $mail->purgeTerminalLogs($days, $dryRun);

        $this->info($dryRun
            ? "$count historique(s) seraient purgés après $days jours."
            : "$count historique(s) purgés après $days jours.");

        return self::SUCCESS;
    }
}
