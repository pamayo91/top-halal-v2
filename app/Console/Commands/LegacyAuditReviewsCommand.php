<?php

namespace App\Console\Commands;

use App\Services\LegacyReviewAuditor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class LegacyAuditReviewsCommand extends Command
{
    protected $signature = 'legacy:audit-reviews {--out=docs/generated/reviews-audit}';
    protected $description = 'Creates an aggregate-only read-only audit of ListingPro reviews.';
    public function handle(LegacyReviewAuditor $auditor): int
    {
        $report = $auditor->audit(); $base = base_path($this->option('out')); File::ensureDirectoryExists(dirname($base));
        File::put($base.'.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        File::put($base.'.md', "# ListingPro Reviews Audit\n\nAggregate-only: no review content or e-mail is exported.\n\n```json\n".json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n```\n");
        $this->info('Legacy review audit written.'); return self::SUCCESS;
    }
}
