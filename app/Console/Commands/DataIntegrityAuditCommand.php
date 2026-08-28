<?php

namespace App\Console\Commands;

use App\Models\{Article, Category, EditorialCategory, EditorialTag, Feature, Location, MediaAsset, Page, Restaurant};
use App\Services\TaxonomyValueClassifier;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{DB, File};

class DataIntegrityAuditCommand extends Command
{
    protected $signature = 'data:integrity-audit {--apply : Repair deterministic date mappings and remove manifestly malicious unused locations}';

    protected $description = 'Audits legacy/V2 dates and taxonomy integrity, then optionally applies safe idempotent repairs.';

    public function handle(TaxonomyValueClassifier $classifier): int
    {
        $before = $this->audit($classifier);
        $changes = ['articles' => 0, 'pages' => 0, 'restaurants' => 0, 'media' => 0, 'malicious_locations_removed' => []];

        if ($this->option('apply')) {
            $changes['articles'] = $this->syncPostDates(Article::class, 'articles', 'post');
            $changes['pages'] = $this->syncPostDates(Page::class, 'pages', 'page');
            $changes['restaurants'] = $this->syncRestaurantDates();
            $changes['media'] = $this->syncMediaDates();

            foreach ($before['geography']['malicious'] as $location) {
                if ($location['restaurants'] !== []) {
                    continue;
                }

                Location::whereKey($location['id'])->delete();
                $changes['malicious_locations_removed'][] = $location;
            }
        }

        $after = $this->audit($classifier);
        $report = $this->markdown($before, $after, $changes);
        File::ensureDirectoryExists(base_path('docs/generated'));
        File::put(base_path('docs/generated/data-integrity-audit.md'), $report);
        File::put(base_path('docs/generated/data-integrity-audit.json'), json_encode([
            'generated_at' => now()->toIso8601String(), 'mode' => $this->option('apply') ? 'apply' : 'dry-run',
            'before' => $before, 'changes' => $changes, 'after' => $after,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info('docs/generated/data-integrity-audit.md');

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function audit(TaxonomyValueClassifier $classifier): array
    {
        return [
            'dates' => $this->dateAudit(),
            'geography' => $this->geographyAudit($classifier),
            'taxonomies' => $this->taxonomyAudit($classifier),
        ];
    }

    /** @return array<string, mixed> */
    private function dateAudit(): array
    {
        return [
            'articles' => $this->postDateAudit('articles', 'post'),
            'pages' => $this->postDateAudit('pages', 'page'),
            'comments' => $this->simpleDateAudit('comments', 'comments', 'comment_ID', 'comment_date_gmt', 'comment_date_gmt', 'legacy_wp_comment_id', fn (Builder $q) => $q->whereIn('comment_approved', ['0', '1'])->whereIn('comment_type', ['', 'comment'])),
            'reviews' => $this->simpleDateAudit('restaurant_reviews', 'posts', 'ID', 'post_date_gmt', 'post_modified_gmt', 'legacy_wp_review_id', fn (Builder $q) => $q->where('post_type', 'lp-reviews')),
            'restaurants' => $this->postDateAudit('restaurants', 'listing', 'legacy_wp_id'),
            'users' => $this->simpleDateAudit('users', 'users', 'ID', 'user_registered', null, 'legacy_wp_user_id'),
            'media' => $this->simpleDateAudit('media_assets', 'posts', 'ID', 'post_date_gmt', 'post_modified_gmt', 'legacy_attachment_id', fn (Builder $q) => $q->where('post_type', 'attachment')),
        ];
    }

    /** @return array<string, mixed> */
    private function postDateAudit(string $table, string $postType, string $legacyKey = 'legacy_wp_id'): array
    {
        $legacy = DB::connection('legacy_wp')->table('posts')->where('post_type', $postType);
        $v2 = DB::table($table);

        return [
            'legacy_total' => (clone $legacy)->count(),
            'legacy_created_available' => (clone $legacy)->where('post_date_gmt', '!=', '0000-00-00 00:00:00')->count(),
            'legacy_updated_available' => (clone $legacy)->where('post_modified_gmt', '!=', '0000-00-00 00:00:00')->count(),
            'v2_total' => (clone $v2)->count(),
            'v2_legacy_created' => (clone $v2)->whereNotNull('legacy_published_at')->count(),
            'v2_legacy_updated' => (clone $v2)->whereNotNull('legacy_modified_at')->count(),
            'v2_publication' => $table === 'restaurants' ? null : (clone $v2)->whereNotNull('published_at')->count(),
            'v2_import_timestamps' => (clone $v2)->selectRaw('min(created_at) as first, max(created_at) as last')->first(),
            'legacy_key' => $legacyKey,
        ];
    }

    /** @param null|callable(Builder): Builder $scope @return array<string, mixed> */
    private function simpleDateAudit(string $v2Table, string $legacyTable, string $legacyIdColumn, string $legacyCreatedColumn, ?string $legacyUpdatedColumn, string $v2LegacyColumn = 'legacy_wp_comment_id', ?callable $scope = null): array
    {
        $legacy = DB::connection('legacy_wp')->table($legacyTable);
        if ($scope) {
            $legacy = $scope($legacy);
        }
        $v2 = DB::table($v2Table);

        return [
            'legacy_total' => (clone $legacy)->count(),
            'legacy_created_available' => (clone $legacy)->where($legacyCreatedColumn, '!=', '0000-00-00 00:00:00')->count(),
            'legacy_updated_available' => $legacyUpdatedColumn ? (clone $legacy)->where($legacyUpdatedColumn, '!=', '0000-00-00 00:00:00')->count() : null,
            'v2_total' => (clone $v2)->count(),
            'v2_with_legacy_identity' => (clone $v2)->whereNotNull($v2LegacyColumn)->count(),
            'v2_import_timestamps' => (clone $v2)->selectRaw('min(created_at) as first, max(created_at) as last')->first(),
            'legacy_key' => $legacyIdColumn,
        ];
    }

    /** @return array<string, mixed> */
    private function geographyAudit(TaxonomyValueClassifier $classifier): array
    {
        $locations = Location::query()->with(['restaurants:id,name,status'])->orderBy('id')->get();
        $classes = ['valid' => [], 'suspect' => [], 'malicious' => [], 'empty' => []];

        foreach ($locations as $location) {
            $class = $classifier->classify($location->name);
            $classes[$class][] = [
                'id' => $location->id, 'legacy_term_id' => $location->legacy_term_id, 'name' => $location->name,
                'slug' => $location->slug, 'parent_id' => $location->parent_id,
                'restaurants' => $location->restaurants->map(fn (Restaurant $restaurant) => [
                    'id' => $restaurant->id, 'legacy_wp_id' => $restaurant->legacy_wp_id,
                    'name' => $restaurant->name, 'status' => $restaurant->status,
                ])->all(),
            ];
        }

        $duplicates = $locations->groupBy(fn (Location $location) => $classifier->normalizedKey($location->name))
            ->filter(fn ($group, $key) => $key !== '' && $group->count() > 1)
            ->map(fn ($group) => $group->map(fn (Location $location) => ['id' => $location->id, 'legacy_term_id' => $location->legacy_term_id, 'name' => $location->name, 'slug' => $location->slug])->values()->all())
            ->values()->all();

        return [
            'total' => $locations->count(),
            'used' => $locations->filter(fn (Location $location) => $location->restaurants->isNotEmpty())->count(),
            'unused' => $locations->filter(fn (Location $location) => $location->restaurants->isEmpty())->count(),
            'valid' => count($classes['valid']), 'suspect' => $classes['suspect'], 'malicious' => $classes['malicious'],
            'empty' => $classes['empty'], 'duplicates' => $duplicates,
        ];
    }

    /** @return array<string, mixed> */
    private function taxonomyAudit(TaxonomyValueClassifier $classifier): array
    {
        $result = [];
        foreach ([
            'categories' => Category::class, 'features' => Feature::class,
            'editorial_categories' => EditorialCategory::class, 'editorial_tags' => EditorialTag::class,
        ] as $name => $model) {
            $items = $model::query()->orderBy('id')->get(['id', 'legacy_term_id', 'name', 'slug']);
            $anomalies = $items->filter(fn ($item) => in_array($classifier->classify($item->name), ['malicious', 'suspect', 'empty'], true))
                ->map(fn ($item) => ['id' => $item->id, 'legacy_term_id' => $item->legacy_term_id, 'name' => $item->name, 'slug' => $item->slug, 'classification' => $classifier->classify($item->name)])->values()->all();
            $result[$name] = ['total' => $items->count(), 'anomalies' => $anomalies];
        }

        return $result;
    }

    private function syncPostDates(string $model, string $table, string $postType): int
    {
        $changed = 0;
        DB::connection('legacy_wp')->table('posts')->where('post_type', $postType)->orderBy('ID')->chunkById(250, function ($posts) use ($model, $table, &$changed): void {
            foreach ($posts as $post) {
                if (! $model::where('legacy_wp_id', $post->ID)->exists()) {
                    continue;
                }
                $published = $this->legacyDate($post->post_date_gmt);
                $modified = $this->legacyDate($post->post_modified_gmt);
                $changed += DB::table($table)->where('legacy_wp_id', $post->ID)->update([
                    'legacy_published_at' => $published, 'legacy_modified_at' => $modified,
                    'published_at' => $published, 'source_type' => 'legacy',
                ]);
            }
        }, 'ID', 'ID');

        return $changed;
    }

    private function syncRestaurantDates(): int
    {
        $changed = 0;
        DB::connection('legacy_wp')->table('posts')->where('post_type', 'listing')->orderBy('ID')->chunkById(250, function ($posts) use (&$changed): void {
            foreach ($posts as $post) {
                $changed += DB::table('restaurants')->where('legacy_wp_id', $post->ID)->update([
                    'legacy_published_at' => $this->legacyDate($post->post_date_gmt),
                    'legacy_modified_at' => $this->legacyDate($post->post_modified_gmt),
                ]);
            }
        }, 'ID', 'ID');

        return $changed;
    }

    private function syncMediaDates(): int
    {
        $changed = 0;
        DB::connection('legacy_wp')->table('posts')->where('post_type', 'attachment')->orderBy('ID')->chunkById(250, function ($posts) use (&$changed): void {
            foreach ($posts as $post) {
                $changed += DB::table('media_assets')->where('legacy_attachment_id', $post->ID)->update([
                    'legacy_created_at' => $this->legacyDate($post->post_date_gmt),
                    'legacy_updated_at' => $this->legacyDate($post->post_modified_gmt),
                ]);
            }
        }, 'ID', 'ID');

        return $changed;
    }

    private function legacyDate(?string $value): ?string
    {
        return $value && $value !== '0000-00-00 00:00:00' ? $value : null;
    }

    /** @param array<string, mixed> $before @param array<string, mixed> $after @param array<string, mixed> $changes */
    private function markdown(array $before, array $after, array $changes): string
    {
        $lines = ['# Audit intégrité des données migrées', '', 'Généré le : `'.now()->toIso8601String().'`', 'Mode : `'.($this->option('apply') ? 'correction appliquée' : 'audit seul').'`', '', '## Dates'];
        foreach ($before['dates'] as $domain => $data) {
            $lines[] = '### '.ucfirst($domain);
            $lines[] = '- Legacy : '.$data['legacy_total'].' enregistrements ; création disponible '.$data['legacy_created_available'].($data['legacy_updated_available'] === null ? '.' : ' ; modification disponible '.$data['legacy_updated_available'].'.');
            $v2LegacyCreated = $data['v2_legacy_created'] ?? $data['v2_with_legacy_identity'];
            $v2LegacyUpdated = $data['v2_legacy_updated'] ?? null;
            $lines[] = '- V2 avant : '.$data['v2_total'].' enregistrements ; identités legacy '.$v2LegacyCreated.($v2LegacyUpdated === null ? '.' : ' ; modification legacy '.$v2LegacyUpdated.'.');
            if (array_key_exists('v2_publication', $data) && $data['v2_publication'] !== null) $lines[] = '- Publication V2 avant : '.$data['v2_publication'].'.';
            if (($after['dates'][$domain]['v2_publication'] ?? null) !== null) $lines[] = '- Publication V2 après : '.$after['dates'][$domain]['v2_publication'].'.';
            $lines[] = '';
        }
        $lines[] = 'Les `created_at`/`updated_at` V2 restent des traces V2 lorsqu’ils ne sont pas déjà historiques. Les dates WordPress sont conservées dans les champs `legacy_*`; les articles/pages utilisent aussi `published_at` pour la publication historique.';
        $lines[] = '';
        $lines[] = '## Géographie';
        $lines[] = '- Total avant : '.$before['geography']['total'].' ; utilisées : '.$before['geography']['used'].' ; inutilisées : '.$before['geography']['unused'].'.';
        $lines[] = '- Valides : '.$before['geography']['valid'].' ; suspectes : '.count($before['geography']['suspect']).' ; manifestement malveillantes : '.count($before['geography']['malicious']).' ; vides : '.count($before['geography']['empty']).'.';
        $lines[] = '- Doublons potentiels (non fusionnés automatiquement) : '.count($before['geography']['duplicates']).'.';
        $lines[] = '- Supprimées : '.count($changes['malicious_locations_removed']).'.';
        $lines[] = '- Après correction : '.$after['geography']['total'].' lieux ; utilisées : '.$after['geography']['used'].' ; inutilisées : '.$after['geography']['unused'].' ; malveillantes restantes : '.count($after['geography']['malicious']).'.';
        foreach ($changes['malicious_locations_removed'] as $location) $lines[] = '- Supprimée V2 #'.$location['id'].' / legacy term #'.$location['legacy_term_id'].' : `'.$location['name'].'` (aucun restaurant associé).';
        foreach ($before['geography']['suspect'] as $location) $lines[] = '- Revue manuelle — suspecte V2 #'.$location['id'].' / legacy term #'.$location['legacy_term_id'].' : `'.$location['name'].'`.';
        foreach ($before['geography']['malicious'] as $location) if ($location['restaurants'] !== []) $lines[] = '- Revue manuelle — malveillante mais associée : V2 #'.$location['id'].' / legacy term #'.$location['legacy_term_id'].' ; restaurants : '.json_encode($location['restaurants'], JSON_UNESCAPED_UNICODE).'.';
        $lines[] = '';
        $lines[] = '## Autres taxonomies';
        foreach ($before['taxonomies'] as $taxonomy => $data) $lines[] = '- '.$taxonomy.' : '.$data['total'].' entrées, '.count($data['anomalies']).' anomalie(s) détectée(s).';
        $lines[] = '';
        $lines[] = '## Corrections idempotentes';
        $lines[] = '- Articles synchronisés : '.$changes['articles'].' ; pages : '.$changes['pages'].' ; restaurants : '.$changes['restaurants'].' ; médias : '.$changes['media'].'.';

        return implode("\n", $lines)."\n";
    }
}
