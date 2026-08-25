<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class LegacyAuditUsersCommand extends Command
{
    protected $signature = 'legacy:audit-users {--out=docs/generated/users-audit}';

    public function handle(): int
    {
        $legacy = DB::connection('legacy_wp');
        $users = $legacy->table('users');
        $roles = [];
        foreach ($legacy->table('usermeta')->where('meta_key', 'tp_capabilities')->get() as $meta) {
            foreach ((array) @unserialize($meta->meta_value, ['allowed_classes' => false]) as $role => $enabled) {
                if ($enabled) $roles[$role] = ($roles[$role] ?? 0) + 1;
            }
        }
        $report = ['total' => $users->count(), 'roles' => $roles, 'empty_email' => (clone $users)->where('user_email', '')->count(), 'hash_formats' => (clone $users)->selectRaw("case when user_pass like '\$P\$%' then 'phpass' when user_pass like '\$wp\$2y\$%' then 'wordpress_bcrypt' else 'other' end format,count(*) count")->groupBy('format')->get(), 'registration' => (clone $users)->selectRaw('min(user_registered) oldest,max(user_registered) newest')->first(), 'claims' => $legacy->table('posts')->where('post_type', 'lp-claims')->count(), 'claimed_listings' => $legacy->table('postmeta')->where('meta_key', 'claimed')->count()];
        $base = base_path($this->option('out'));
        File::put($base.'.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        File::put($base.'.md', "# Users Audit\n\n```json\n".json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n```\n");
        $this->info('Users audit written.');

        return self::SUCCESS;
    }
}
