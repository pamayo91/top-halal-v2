<?php

namespace App\Console\Commands;

use App\Services\CommuneTextNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncCommuneReferenceCommand extends Command
{
    protected $signature = 'communes:sync-reference {--fresh : Replace the local reference with the versioned source}';

    protected $description = 'Load the versioned official French commune reference for public search.';

    public function handle(CommuneTextNormalizer $normalizer): int
    {
        if ($this->option('fresh')) DB::table('commune_references')->delete();

        $now = now();
        $rows = [];
        foreach (file(database_path('data/communes-france-2026.tsv'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (str_starts_with($line, '#')) continue;
            [$code, $name, $department] = explode("\t", $line, 3);
            $rows[] = ['city_code' => $code, 'city_name' => $name, 'department_code' => $department, 'normalized_name' => $normalizer->normalize($name), 'created_at' => $now, 'updated_at' => $now];
            if (count($rows) === 500) {
                DB::table('commune_references')->upsert($rows, ['city_code'], ['city_name', 'department_code', 'normalized_name', 'updated_at']);
                $rows = [];
            }
        }
        if ($rows !== []) DB::table('commune_references')->upsert($rows, ['city_code'], ['city_name', 'department_code', 'normalized_name', 'updated_at']);

        $this->info('Official commune reference synchronized locally.');

        return self::SUCCESS;
    }
}
