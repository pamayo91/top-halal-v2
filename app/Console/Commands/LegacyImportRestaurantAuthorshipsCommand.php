<?php

namespace App\Console\Commands;

use App\Models\LegacyRestaurantAuthorship;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class LegacyImportRestaurantAuthorshipsCommand extends Command
{
    protected $signature = 'legacy:import-restaurant-authorships
        {--apply : Create audited relationship rows; otherwise read-only.}
        {--batch= : UUID written to new rows for an exact rollback scope.}
        {--out=docs/generated/legacy-restaurant-authorships : Private report directory.}';

    protected $description = 'Audit or import only legacy listing-author to V2 restaurant relationships.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $batch = $this->option('batch') ?: (string) Str::uuid();
        if ($apply && ! Str::isUuid($batch)) {
            $this->error('L’option --batch doit être un UUID valide.');
            return self::FAILURE;
        }

        $report = $this->reconcile($this->sourceRows(), $batch);
        if ($apply) $this->persist($report, $batch);
        unset($report['eligible_rows']);
        $report['mode'] = $apply ? 'apply' : 'dry-run';
        $report['batch'] = $apply ? $batch : null;
        $this->writeReport($report);
        $this->table(['Metric', 'Count'], collect($report['summary'])->map(fn ($count, $metric) => [$metric, $count])->all());
        $this->info($apply ? "Relations créées sous le lot {$batch}." : 'Lecture seule : aucune relation V2 créée.');
        return self::SUCCESS;
    }

    /** @return array<int, object> */
    private function sourceRows(): array
    {
        return DB::connection('legacy_wp')->table('posts as p')->join('users as u', 'u.ID', '=', 'p.post_author')
            ->select(['p.ID', 'p.post_author', 'p.post_status'])
            ->where('p.post_type', 'listing')->where('p.post_author', '>', 0)->orderBy('p.ID')->get()->all();
    }

    /** @param array<int, object> $source @return array<string, mixed> */
    private function reconcile(array $source, string $batch): array
    {
        $userIds = collect($source)->pluck('post_author')->map(fn ($id) => (int) $id)->unique()->values();
        $restaurantIds = collect($source)->pluck('ID')->map(fn ($id) => (int) $id)->values();
        $users = User::withTrashed()->whereIn('legacy_wp_user_id', $userIds)->get()->keyBy('legacy_wp_user_id');
        $restaurants = Restaurant::withTrashed()->whereIn('legacy_wp_id', $restaurantIds)->get()->keyBy('legacy_wp_id');
        $summary = ['source_links' => count($source), 'source_non_published' => 0, 'user_missing' => 0, 'user_trashed' => 0, 'restaurant_missing' => 0, 'restaurant_trashed' => 0, 'eligible' => 0, 'created' => 0, 'existing' => 0, 'conflicts' => 0];
        $eligible = []; $anomalies = [];
        foreach ($source as $sourceRow) {
            $row = ['legacy_wp_id' => (int) $sourceRow->ID, 'legacy_wp_user_id' => (int) $sourceRow->post_author, 'source_post_status' => (string) $sourceRow->post_status];
            if ($sourceRow->post_status !== 'publish') { $summary['source_non_published']++; $anomalies[] = $this->anomaly($row, 'source_listing_not_published'); continue; }
            $user = $users->get($row['legacy_wp_user_id']);
            if (! $user) { $summary['user_missing']++; $anomalies[] = $this->anomaly($row, 'v2_user_missing'); continue; }
            if ($user->trashed()) { $summary['user_trashed']++; $anomalies[] = $this->anomaly($row, 'v2_user_trashed'); continue; }
            $restaurant = $restaurants->get($row['legacy_wp_id']);
            if (! $restaurant) { $summary['restaurant_missing']++; $anomalies[] = $this->anomaly($row, 'v2_restaurant_missing'); continue; }
            if ($restaurant->trashed()) { $summary['restaurant_trashed']++; $anomalies[] = $this->anomaly($row, 'v2_restaurant_trashed'); continue; }
            $summary['eligible']++;
            $eligible[] = $row + ['user_id' => $user->id, 'restaurant_id' => $restaurant->id, 'import_batch' => $batch];
        }
        return compact('summary', 'eligible', 'anomalies');
    }

    /** @param array<string, mixed> $report */
    private function persist(array &$report, string $batch): void
    {
        foreach ($report['eligible_rows'] as $row) {
            $existing = LegacyRestaurantAuthorship::where('legacy_wp_id', $row['legacy_wp_id'])->first();
            if ($existing) {
                if ($existing->user_id !== $row['user_id'] || $existing->restaurant_id !== $row['restaurant_id']) { $report['summary']['conflicts']++; $report['anomalies'][] = $this->anomaly($row, 'existing_mapping_conflict'); }
                else $report['summary']['existing']++;
                continue;
            }
            LegacyRestaurantAuthorship::create($row + ['import_batch' => $batch]);
            $report['summary']['created']++;
        }
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function anomaly(array $row, string $reason): array { return $row + ['reason' => $reason]; }

    /** @param array<string, mixed> $report */
    private function writeReport(array $report): void
    {
        $directory = base_path($this->option('out')); File::ensureDirectoryExists($directory); $stamp = now()->format('Ymd-His');
        File::put("{$directory}/{$stamp}.json", json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $lines = ['# Audit relation auteurs WordPress → restaurants V2', '', 'Mode : `'.$report['mode'].'`.', 'Portée : relation historique uniquement ; aucune revendication, rôle, permission ou fiche restaurant n’est modifié.', '', '## Résumé', ''];
        foreach ($report['summary'] as $name => $count) $lines[] = "- {$name} : {$count}";
        $lines[] = ''; $lines[] = 'Les anomalies complètes (identifiants techniques seulement, sans e-mail) sont dans le JSON associé.';
        File::put("{$directory}/{$stamp}.md", implode("\n", $lines)."\n");
    }
}
