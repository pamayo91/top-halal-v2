<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

class LegacyMigrateUsersCommand extends Command
{
    protected $signature = 'legacy:migrate-users {--ids= : Explicit comma-separated pilot IDs (max 10)} {--dry-run : Inspect only} {--apply : Persist the reviewed pilot} {--out=docs/generated/users-migration-sample}';

    public function handle(): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', (string) $this->option('ids'))))));
        if ($ids === [] || count($ids) > 10 || ($this->option('dry-run') && $this->option('apply'))) {
            $this->error('Use 1-10 explicit --ids and at most one of --dry-run/--apply.');

            return self::FAILURE;
        }
        if ($this->option('apply') && ! filled(env('LEGACY_MIGRATION_TEMP_PASSWORD'))) {
            $this->error('The server-only legacy migration password is not configured.');

            return self::FAILURE;
        }

        $legacy = DB::connection('legacy_wp');
        $rows = $legacy->table('users')->whereIn('ID', $ids)->orderBy('ID')->get();
        $report = ['mode' => $this->option('apply') ? 'apply' : 'dry-run', 'ids' => $ids, 'items' => []];

        foreach ($rows as $row) {
            $item = [
                'legacy_wp_user_id' => (int) $row->ID,
                'role' => 'user',
                'email_present' => $row->user_email !== '',
                'password_strategy' => 'laravel_temporary_hash_must_change',
                'anomalies' => $row->user_email === '' ? ['missing_email'] : [],
            ];
            if ($this->option('apply') && $row->user_email !== '') {
                DB::transaction(function () use ($row): void {
                    $user = User::firstOrNew(['legacy_wp_user_id' => $row->ID]);
                    $user->forceFill([
                        'legacy_wp_user_id' => $row->ID,
                        'name' => trim($row->display_name) ?: 'Utilisateur',
                        'email' => $row->user_email,
                        'password' => Hash::make((string) env('LEGACY_MIGRATION_TEMP_PASSWORD')),
                        'role' => 'user',
                        'status' => 'active',
                        'must_change_password' => true,
                        'created_at' => $row->user_registered,
                    ]);
                    $user->save();
                });
            }
            $report['items'][] = $item;
        }
        foreach (array_diff($ids, $rows->pluck('ID')->map(fn ($id) => (int) $id)->all()) as $id) {
            $report['items'][] = ['legacy_wp_user_id' => $id, 'anomalies' => ['legacy_user_not_found']];
        }

        $base = base_path($this->option('out'));
        File::put($base.'.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        File::put($base.'.md', "# Users migration pilot\n\n```json\n".json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n```\n");
        $this->info('Users migration report written.');

        return self::SUCCESS;
    }
}
