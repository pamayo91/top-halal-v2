<?php

namespace App\Console\Commands;

use App\Services\TransactionalMailService;
use Illuminate\Console\Command;

class ReconcileEmailDeliveryLogs extends Command
{
    protected $signature = 'emails:reconcile-delivery-logs';
    protected $description = 'Expire les e-mails en attente dont le job Laravel n’existe plus.';

    public function handle(TransactionalMailService $mail): int
    {
        $this->info($mail->expireMissingQueuedJobs().' historique(s) expiré(s).');

        return self::SUCCESS;
    }
}
