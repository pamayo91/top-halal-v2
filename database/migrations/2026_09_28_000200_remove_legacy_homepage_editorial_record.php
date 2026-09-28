<?php

use App\Models\{ContentMedia, Page, RedirectRule};
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        // This is the V2-only legacy WordPress homepage (audited before this
        // release: legacy_wp_id 12755, slug home, legacy_url /home/). The
        // actual homepage has always been the dedicated / controller action.
        $page = Page::withTrashed()->where('legacy_wp_id', 12755)->where('slug', 'home')->where('legacy_url', '/home/')->first();
        if ($page === null) return;

        RedirectRule::updateOrCreate(
            ['source_path' => '/home', 'match_type' => 'exact', 'query_pattern' => null],
            ['destination' => '/', 'status_code' => 301, 'preserve_query' => false, 'priority' => 100, 'is_active' => true, 'origin' => 'homepage_legacy_cleanup', 'source_rule' => 'Removed V2 legacy homepage record'],
        );
        ContentMedia::where('content_type', 'page')->where('content_id', $page->id)->delete();
        $page->forceDelete();
    }

    public function down(): void
    {
        // Deliberately irreversible: the owner retains the external copy and
        // restoring editorial data belongs to an explicit data operation.
    }
};
