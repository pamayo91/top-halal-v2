<?php

namespace App\Console\Commands;

use App\Models\{Article, Page, Restaurant};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AuditLegacyUrlsCommand extends Command
{
    protected $signature = 'seo:audit-legacy-urls {--out=docs/generated/legacy-url-audit.json}';
    protected $description = 'Audits deterministic legacy-to-V2 URLs and reports collisions or missing published targets.';
    public function handle(): int
    {
        $articles = Article::where('status', 'published')->get(); $pages = Page::where('status', 'published')->get(); $restaurants = Restaurant::where('status', 'published')->get();
        $slugs = $articles->pluck('legacy_wp_id', 'slug')->all(); $collisions = [];
        foreach ($pages as $page) if (isset($slugs[$page->slug])) $collisions[] = ['slug' => $page->slug, 'article_legacy_id' => $slugs[$page->slug], 'page_legacy_id' => $page->legacy_wp_id];
        $report = [
            'generated_at' => now()->toAtomString(),
            'published' => ['articles' => $articles->count(), 'pages' => $pages->count(), 'restaurants' => $restaurants->count()],
            'mappings' => [
                'articles' => $articles->map(fn ($x) => ['legacy' => $x->legacy_url, 'v2' => '/'.$x->slug])->all(),
                'pages' => $pages->map(fn ($x) => ['legacy' => $x->legacy_url, 'v2' => '/'.$x->slug])->all(),
                'restaurants' => $restaurants->map(fn ($x) => ['legacy' => '/restaurants/'.$x->slug, 'v2' => '/resto/'.$x->slug])->all(),
            ],
            'anomalies' => ['editorial_slug_collisions' => $collisions],
        ];
        File::put(base_path($this->option('out')), json_encode($report, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
        $this->info("{$articles->count()} articles, {$pages->count()} pages and {$restaurants->count()} restaurants audited; ".count($collisions).' editorial collisions.');
        return $collisions ? self::FAILURE : self::SUCCESS;
    }
}
