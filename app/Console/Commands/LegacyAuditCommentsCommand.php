<?php

namespace App\Console\Commands;

use App\Services\LegacyCommentAuditor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class LegacyAuditCommentsCommand extends Command
{
    protected $signature = 'legacy:audit-comments {--out=docs/generated/comments-audit}';

    protected $description = 'Creates a reproducible, aggregate-only audit of legacy WordPress comments.';

    public function handle(LegacyCommentAuditor $auditor): int
    {
        $report = $auditor->audit();
        $base = base_path($this->option('out'));
        File::put($base.'.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $lines = ['# Legacy Comments Audit', '', 'Aggregate-only report: no author email or comment content is exported.', '', '```json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), '```'];
        File::put($base.'.md', implode("\n", $lines)."\n");
        $this->info('Legacy comment audit written.');
        return self::SUCCESS;
    }
}
