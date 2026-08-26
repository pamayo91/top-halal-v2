<?php

namespace App\Console\Commands;

use App\Models\{Article, Page, RedirectRule};
use App\Services\RedirectResolver;
use Illuminate\Console\Command;

class ApplySitemapCleanupCommand extends Command
{
    protected $signature = 'seo:apply-sitemap-cleanup {--dry-run}';
    protected $description = 'Applies the approved non-restaurant sitemap cleanup without deleting migrated records.';

    public function handle(): int
    {
        $rules = [
            '/home' => '/', '/payment-success-2' => '/', '/blog-2' => '/blog', '/erreur-paiement' => '/',
            '/payment-checkout' => '/', '/payment-fail' => '/', '/payment-success' => '/', '/submit-listing' => '/', '/hello' => '/',
        ];
        foreach ($rules as $source => $destination) {
            $content = Page::where('slug', ltrim($source, '/'))->first() ?? Article::where('slug', ltrim($source, '/'))->first();
            if (! $this->option('dry-run') && $content) $content->update(['status' => 'redirected', 'seo_robots' => 'noindex,follow']);
            if (! $this->option('dry-run')) RedirectRule::updateOrCreate(
                ['source_path' => $source, 'match_type' => 'exact', 'query_pattern' => null],
                ['destination' => $destination, 'status_code' => 301, 'preserve_query' => false, 'priority' => 1, 'is_active' => true, 'origin' => 'seo_cleanup', 'source_rule' => 'Approved non-restaurant sitemap cleanup'],
            );
            $this->line("{$source} → {$destination}".($content ? ' (record retained, redirected)' : ' (redirect-only)'));
        }
        if (! $this->option('dry-run')) app(RedirectResolver::class)->clearCache();
        return self::SUCCESS;
    }
}
