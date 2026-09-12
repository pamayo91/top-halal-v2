<?php

namespace App\Console\Commands;

use App\Models\ContributionVerification;
use Illuminate\Console\Command;

class PurgeContributionVerifications extends Command
{
    protected $signature = 'contributions:purge-verifications {--dry-run : Compte sans supprimer} {--batch=500 : Nombre maximum de lignes traitées par lot}';

    protected $description = 'Purge les vérifications de contribution consommées ou expirées depuis plus de sept jours.';

    public function handle(): int
    {
        $cutoff = now()->subDays(7);
        $batchSize = max(1, min(1000, (int) $this->option('batch')));
        $eligible = $this->eligible($cutoff);
        $count = $eligible->count();

        $this->info("Vérifications éligibles : $count.");

        if ($this->option('dry-run')) {
            $this->info('Vérifications supprimées : 0 (simulation).');

            return self::SUCCESS;
        }

        $deleted = 0;
        $eligible->orderBy('id')->chunkById($batchSize, function ($verifications) use (&$deleted, $cutoff): void {
            $deleted += $this->eligible($cutoff)
                ->whereKey($verifications->pluck('id'))
                ->delete();
        });

        $this->info("Vérifications supprimées : $deleted.");

        return self::SUCCESS;
    }

    private function eligible(\DateTimeInterface $cutoff)
    {
        return ContributionVerification::query()->where(function ($query) use ($cutoff): void {
            $query->where(function ($query) use ($cutoff): void {
                $query->whereNotNull('used_at')->where('used_at', '<', $cutoff);
            })->orWhere(function ($query) use ($cutoff): void {
                $query->whereNull('used_at')->where('expires_at', '<', $cutoff);
            });
        });
    }
}
