<?php

namespace App\Console\Commands;

use App\Models\{Article, Category, Feature, Location, Page};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class AuditNonRestaurantSitemapCommand extends Command
{
    protected $signature = 'seo:audit-non-restaurant-sitemap {--out=docs/generated/non-restaurant-sitemap-audit.md}';
    protected $description = 'Writes a read-only exhaustive audit of non-restaurant public URLs and sitemap eligibility.';

    public function handle(): int
    {
        $base = rtrim(config('app.url'), '/'); $rows = [];
        $add = function (string $path, string $type, ?int $legacyId, string $status, bool $indexable, bool $inSitemap, string $recommendation, string $reason) use (&$rows, $base): void {
            $rows[] = compact('path', 'type', 'legacyId', 'status', 'indexable', 'inSitemap', 'recommendation', 'reason') + ['url' => $base.$path, 'canonical' => $base.$path];
        };
        $add('/', 'homepage', null, 'published', true, true, 'CONSERVER', 'Homepage canonique.');

        foreach (Article::orderBy('id')->get() as $item) $this->addEditorial($add, $item, 'article');
        foreach (Page::orderBy('id')->get() as $item) $this->addEditorial($add, $item, 'page');
        foreach (Category::orderBy('id')->get() as $item) $add('/specialites/'.$item->slug, 'category', $item->legacy_term_id, 'active', false, false, 'CONSERVER', 'Taxonomie non indexable tant qu’aucune landing page éditoriale n’est validée.');
        foreach (Feature::orderBy('id')->get() as $item) $add('/service/'.$item->slug, 'feature', $item->legacy_term_id, 'active', false, false, 'CONSERVER', 'Taxonomie non indexable tant qu’aucune landing page éditoriale n’est validée.');
        foreach (Location::orderBy('id')->get() as $item) $add('/restos/'.$item->slug, 'geography', $item->legacy_term_id, 'active', false, false, 'CONSERVER', 'Géographie non indexable tant qu’aucune landing page éditoriale n’est validée.');
        foreach (['/health', '/login', '/register', '/forgot-password', '/account', '/verify-email', '/sitemap.xml', '/robots.txt', '/_preview/post/{legacyId}', '/_preview/page/{legacyId}', '/_preview/restaurant/{legacyId}'] as $path) {
            $add($path, 'system', null, 'system', false, false, 'CONSERVER', 'Route technique/authentification/preview : jamais indexable ni incluse dans le sitemap.');
        }

        usort($rows, fn (array $a, array $b) => [$a['type'], $a['url']] <=> [$b['type'], $b['url']]);
        $summary = collect($rows)->groupBy('recommendation')->map->count()->all();
        $lines = ['# Audit sitemap V2 — URLs hors restaurants', '', 'Généré le '.now()->toAtomString().'. Cet audit est en lecture seule ; il décrit le sitemap effectif avant correction.', '', '## Synthèse', '', '- URLs auditées hors restaurants : **'.count($rows).'**', '- À conserver : **'.($summary['CONSERVER'] ?? 0).'**', '- À retirer du sitemap : **'.($summary['RETIRER DU SITEMAP'] ?? 0).'**', '- À supprimer/rediriger : **'.($summary['SUPPRIMER-REDIRIGER'] ?? 0).'**', '', '## Détail exhaustif', '', '| URL | Type | legacy_wp_id | Statut V2 | Indexable | Sitemap | Canonical | Recommandation | Raison |', '| --- | --- | ---: | --- | --- | --- | --- | --- | --- |'];
        foreach ($rows as $row) $lines[] = '| '.implode(' | ', [
            $this->cell($row['url']), $row['type'], $row['legacyId'] ?? '—', $row['status'], $row['indexable'] ? 'oui' : 'non', $row['inSitemap'] ? 'oui' : 'non', $this->cell($row['canonical']), $row['recommendation'], $this->cell($row['reason']),
        ]).' |';
        File::put(base_path($this->option('out')), implode("\n", $lines)."\n");
        $this->info(count($rows).' URLs hors restaurants auditées.');
        return self::SUCCESS;
    }

    private function addEditorial(callable $add, object $item, string $type): void
    {
        $path = '/'.$item->slug; $status = $item->status; $indexable = $status === 'published' && ! Str::contains((string) $item->seo_robots, 'noindex');
        $sitemap = $status === 'published'; $slug = Str::lower($item->slug);
        $explicitHome = $slug === 'home'; $explicitPayment = $slug === 'payment-success-2';
        $suspect = $explicitHome || $explicitPayment || Str::contains($slug, ['payment', 'checkout', 'login', 'register', 'account', 'dashboard', 'submit-listing', 'claim', 'listingpro', 'test', 'demo', 'search']);
        $empty = blank(trim(strip_tags((string) $item->content_html)));
        if ($explicitHome || $explicitPayment) [$recommendation, $reason] = ['SUPPRIMER-REDIRIGER', 'Décision validée : redirection 301 vers `/`, non indexable et exclue du sitemap.'];
        elseif ($suspect || $empty || ! $indexable) [$recommendation, $reason] = ['RETIRER DU SITEMAP', $empty ? 'Contenu vide : revue métier avant toute suppression ou redirection.' : 'Page technique/suspecte ou non indexable : revue métier requise avant suppression/redirection.'];
        else [$recommendation, $reason] = ['CONSERVER', 'Contenu éditorial publié sans signal technique détecté.'];
        $add($path, $type, $item->legacy_wp_id, $status, $indexable && ! $explicitHome && ! $explicitPayment, $sitemap, $recommendation, $reason);
    }

    private function cell(string $value): string { return str_replace(['|', "\n", "\r"], ['\\|', ' ', ' '], $value); }
}
