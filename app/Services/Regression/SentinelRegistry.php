<?php

namespace App\Services\Regression;

use App\Models\{Article, Page, RedirectRule, RegressionSentinel, Restaurant};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\{DB, Storage};

class SentinelRegistry
{
    /** @return array<string, int> */
    public function counts(): array
    {
        return collect([
            'restaurants', 'media_assets', 'restaurant_media', 'articles', 'pages',
            'categories', 'features', 'restaurant_reviews', 'comments', 'users',
            'restaurant_claims', 'redirect_rules',
        ])->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()])->all();
    }

    /** @return array<string, array{subject_type: string, subject_id: int|null, route_path: string|null, baseline: array<string, mixed>}> */
    public function discover(): array
    {
        $sentinels = [];
        $restaurant = fn (): Builder => Restaurant::query()->where('status', 'published');

        $this->addRestaurant($sentinels, 'restaurant.gallery', (clone $restaurant())->has('media.asset', '>=', 2)->orderByDesc('id')->first());
        $this->addRestaurant($sentinels, 'restaurant.single_media', (clone $restaurant())->has('media.asset', '=', 1)->orderByDesc('id')->first());
        $this->addRestaurant($sentinels, 'restaurant.no_media', (clone $restaurant())->doesntHave('media')->orderByDesc('id')->first());
        $this->addRestaurant($sentinels, 'restaurant.categories', (clone $restaurant())->has('categories')->orderByDesc('id')->first());
        $this->addRestaurant($sentinels, 'restaurant.features', (clone $restaurant())->has('features')->orderByDesc('id')->first());
        $this->addRestaurant($sentinels, 'restaurant.reviews', (clone $restaurant())->has('reviews')->orderByDesc('id')->first());
        $this->addRestaurant($sentinels, 'restaurant.structured_address', (clone $restaurant())->whereNotNull('address_line1')->whereNotNull('city_code')->whereNotNull('latitude')->whereNotNull('longitude')->orderByDesc('id')->first());
        $this->addRestaurant($sentinels, 'restaurant.o_sha', (clone $restaurant())->whereRaw('LOWER(name) = ?', ['o sha'])->first());

        $this->addArticle($sentinels, 'article.featured_media', Article::query()->where('status', 'published')->whereHas('featuredMedia.asset')->orderByDesc('id')->first());
        $this->addArticle($sentinels, 'article.inline_media', Article::query()->where('status', 'published')->whereHas('contentMedia.asset', fn (Builder $query) => $query->where('role', 'inline'))->orderByDesc('id')->first());
        $this->addArticle($sentinels, 'article.no_media', Article::query()->where('status', 'published')->doesntHave('contentMedia')->orderByDesc('id')->first());

        if ($page = Page::query()->where('status', 'published')->orderByDesc('id')->first()) {
            $sentinels['page.editorial'] = ['subject_type' => 'page', 'subject_id' => $page->id, 'route_path' => '/'.$page->slug, 'baseline' => [
                'id' => $page->id, 'legacy_wp_id' => $page->legacy_wp_id, 'slug' => $page->slug, 'status' => $page->status,
            ]];
        }

        if ($redirect = RedirectRule::query()->where('is_active', true)->where('match_type', 'exact')->whereIn('status_code', [301, 302, 410])->orderByDesc('priority')->first()) {
            $sentinels['redirect.representative'] = ['subject_type' => 'redirect', 'subject_id' => $redirect->id, 'route_path' => $redirect->source_path, 'baseline' => [
                'id' => $redirect->id, 'source_path' => $redirect->source_path, 'status_code' => $redirect->status_code, 'destination' => $redirect->destination,
            ]];
        }

        return $sentinels;
    }

    /** @param array<string, array{subject_type: string, subject_id: int|null, route_path: string|null, baseline: array<string, mixed>}> $sentinels */
    private function addRestaurant(array &$sentinels, string $key, ?Restaurant $restaurant): void
    {
        if (! $restaurant) {
            return;
        }

        $restaurant->load(['media.asset.variants', 'categories', 'features', 'locations', 'reviews', 'openingHours']);
        $sentinels[$key] = ['subject_type' => 'restaurant', 'subject_id' => $restaurant->id, 'route_path' => '/resto/'.$restaurant->slug, 'baseline' => [
            'id' => $restaurant->id,
            'legacy_wp_id' => $restaurant->legacy_wp_id,
            'slug' => $restaurant->slug,
            'status' => $restaurant->status,
            'categories' => $restaurant->categories->pluck('id')->sort()->values()->all(),
            'features' => $restaurant->features->pluck('id')->sort()->values()->all(),
            'locations' => $restaurant->locations->pluck('id')->sort()->values()->all(),
            'reviews' => $restaurant->reviews->pluck('id')->sort()->values()->all(),
            'opening_hours' => $restaurant->openingHours->pluck('id')->sort()->values()->all(),
            'address' => $restaurant->only(['address_line1', 'address_line2', 'postal_code', 'city_name', 'city_code', 'country_code', 'latitude', 'longitude']),
            'media' => $restaurant->media->map(fn ($media): array => [
                'id' => $media->id, 'media_asset_id' => $media->media_asset_id, 'legacy_attachment_id' => $media->legacy_attachment_id,
                'asset' => $media->asset ? $this->assetBaseline($media->asset) : null,
            ])->values()->all(),
        ]];
    }

    /** @param array<string, array{subject_type: string, subject_id: int|null, route_path: string|null, baseline: array<string, mixed>}> $sentinels */
    private function addArticle(array &$sentinels, string $key, ?Article $article): void
    {
        if (! $article) {
            return;
        }

        $article->load(['categories', 'tags', 'contentMedia.asset.variants']);
        $sentinels[$key] = ['subject_type' => 'article', 'subject_id' => $article->id, 'route_path' => '/'.$article->slug, 'baseline' => [
            'id' => $article->id, 'legacy_wp_id' => $article->legacy_wp_id, 'slug' => $article->slug, 'status' => $article->status,
            'categories' => $article->categories->pluck('id')->sort()->values()->all(),
            'tags' => $article->tags->pluck('id')->sort()->values()->all(),
            'media' => $article->contentMedia->map(fn ($media): array => [
                'id' => $media->id, 'role' => $media->role, 'media_asset_id' => $media->media_asset_id,
                'legacy_attachment_id' => $media->legacy_attachment_id, 'asset' => $media->asset ? $this->assetBaseline($media->asset) : null,
            ])->values()->all(),
        ]];
    }

    /** @return array<string, mixed> */
    private function assetBaseline(object $asset): array
    {
        return [
            'id' => $asset->id, 'original_path' => $asset->original_path, 'mime' => $asset->mime, 'status' => $asset->status,
            'variants' => $asset->variants->map(fn ($variant): array => ['id' => $variant->id, 'path' => $variant->path, 'format' => $variant->format, 'width' => $variant->width])->values()->all(),
        ];
    }

    public function persist(bool $refresh = false): int
    {
        $discovered = $this->discover();
        foreach ($discovered as $key => $sentinel) {
            $existing = RegressionSentinel::where('key', $key)->first();
            if ($existing && ! $refresh) {
                continue;
            }
            RegressionSentinel::updateOrCreate(['key' => $key], $sentinel);
        }
        return count($discovered);
    }

    /** @return array{errors: array<int, string>, urls: array<string, string>, media_urls: array<int, string>, counts: array<string, int>} */
    public function verify(): array
    {
        $errors = [];
        $urls = ['home' => '/', 'search' => '/restaurants', 'blog' => '/blog', 'login' => '/login', 'not_found' => '/__regression_missing_404'];
        $mediaUrls = [];
        $sentinels = RegressionSentinel::query()->orderBy('key')->get();
        if ($sentinels->isEmpty()) {
            return ['errors' => ['No regression sentinels have been registered. Run regression:sentinels --refresh-baseline on preproduction.'], 'urls' => $urls, 'media_urls' => [], 'counts' => $this->counts()];
        }

        $global = $sentinels->firstWhere('key', 'database.counts');
        if (! $global) {
            $errors[] = 'Missing database.counts regression baseline.';
        } else {
            foreach (($global->baseline['counts'] ?? []) as $table => $minimum) {
                $actual = DB::table($table)->count();
                if ($actual < $minimum) $errors[] = "Unexpected count decrease for {$table}: {$actual} < {$minimum}.";
            }
        }

        foreach ($sentinels->where('key', '!=', 'database.counts') as $sentinel) {
            if ($sentinel->route_path) $urls[$sentinel->key] = $sentinel->route_path;
            $this->verifySentinel($sentinel, $errors, $mediaUrls);
        }

        foreach ([Article::class => 'articles', Page::class => 'pages'] as $model => $table) {
            if ($model::query()->where('content_html', 'like', '%wp-content%')->orWhere('content_html', 'like', '%wp-contenu%')->exists()) {
                $errors[] = "Legacy upload URL found in {$table}.";
            }
        }

        return ['errors' => $errors, 'urls' => $urls, 'media_urls' => array_values(array_unique($mediaUrls)), 'counts' => $this->counts()];
    }

    /** @param array<int, string> $errors @param array<int, string> $mediaUrls */
    private function verifySentinel(RegressionSentinel $sentinel, array &$errors, array &$mediaUrls): void
    {
        $baseline = $sentinel->baseline;
        $record = match ($sentinel->subject_type) {
            'restaurant' => Restaurant::withTrashed()->find($sentinel->subject_id),
            'article' => Article::find($sentinel->subject_id),
            'page' => Page::find($sentinel->subject_id),
            'redirect' => RedirectRule::find($sentinel->subject_id),
            default => null,
        };
        if (! $record) {
            $errors[] = "{$sentinel->key}: sentinel record no longer exists.";
            return;
        }
        foreach (['legacy_wp_id', 'slug', 'status'] as $field) {
            if (array_key_exists($field, $baseline) && (string) $record->{$field} !== (string) $baseline[$field]) $errors[] = "{$sentinel->key}: {$field} changed unexpectedly.";
        }
        if ($sentinel->subject_type === 'restaurant') $this->verifyRestaurant($sentinel->key, $record, $baseline, $errors, $mediaUrls);
        if ($sentinel->subject_type === 'article') $this->verifyArticle($sentinel->key, $record, $baseline, $errors, $mediaUrls);
        if ($sentinel->subject_type === 'redirect' && ((int) $record->status_code !== (int) $baseline['status_code'] || $record->source_path !== $baseline['source_path'])) $errors[] = "{$sentinel->key}: redirect changed unexpectedly.";
    }

    /** @param array<string, mixed> $baseline @param array<int, string> $errors @param array<int, string> $mediaUrls */
    private function verifyRestaurant(string $key, Restaurant $restaurant, array $baseline, array &$errors, array &$mediaUrls): void
    {
        $restaurant->load(['media.asset.variants', 'categories', 'features', 'locations', 'reviews', 'openingHours']);
        foreach (['categories' => 'categories', 'features' => 'features', 'locations' => 'locations', 'reviews' => 'reviews', 'opening_hours' => 'openingHours'] as $baselineKey => $relation) {
            $actual = $restaurant->{$relation}->pluck('id')->sort()->values()->all();
            if ($actual !== ($baseline[$baselineKey] ?? [])) $errors[] = "{$key}: {$baselineKey} relation changed unexpectedly.";
        }
        if (($baseline['address'] ?? []) !== $restaurant->only(array_keys($baseline['address'] ?? []))) $errors[] = "{$key}: structured address or GPS changed unexpectedly.";
        $this->verifyMedia($key, $baseline['media'] ?? [], $restaurant->media->keyBy('id')->all(), $errors, $mediaUrls);
    }

    /** @param array<string, mixed> $baseline @param array<int, string> $errors @param array<int, string> $mediaUrls */
    private function verifyArticle(string $key, Article $article, array $baseline, array &$errors, array &$mediaUrls): void
    {
        $article->load(['categories', 'tags', 'contentMedia.asset.variants']);
        foreach (['categories' => 'categories', 'tags' => 'tags'] as $baselineKey => $relation) {
            if ($article->{$relation}->pluck('id')->sort()->values()->all() !== ($baseline[$baselineKey] ?? [])) $errors[] = "{$key}: {$baselineKey} relation changed unexpectedly.";
        }
        $this->verifyMedia($key, $baseline['media'] ?? [], $article->contentMedia->keyBy('id')->all(), $errors, $mediaUrls);
    }

    /** @param array<int, array<string, mixed>> $baselineMedia @param array<int, object> $actualMedia @param array<int, string> $errors @param array<int, string> $mediaUrls */
    private function verifyMedia(string $key, array $baselineMedia, array $actualMedia, array &$errors, array &$mediaUrls): void
    {
        $disk = Storage::disk(config('legacy-media.disk'));
        foreach ($baselineMedia as $expected) {
            $media = $actualMedia[$expected['id']] ?? null;
            if (! $media || $media->media_asset_id !== $expected['media_asset_id']) {
                $errors[] = "{$key}: media relation #{$expected['id']} changed or disappeared.";
                continue;
            }
            $asset = $media->asset;
            if (! $asset || $asset->id !== ($expected['asset']['id'] ?? null)) {
                $errors[] = "{$key}: media asset for relation #{$expected['id']} disappeared.";
                continue;
            }
            if (preg_match('#wp-conten(?:t|u)#i', $asset->original_path) || ! $disk->exists($asset->original_path)) $errors[] = "{$key}: V2 source media file is unavailable or legacy.";
            $variants = $asset->variants->keyBy('id');
            foreach (($expected['asset']['variants'] ?? []) as $variant) {
                if (! isset($variants[$variant['id']]) || ! $disk->exists($variant['path'])) $errors[] = "{$key}: expected media variant is unavailable.";
            }
            if (! empty($expected['asset']['variants']) && $asset->variants->isEmpty()) $errors[] = "{$key}: all expected media variants disappeared.";
            if (str_starts_with($asset->mime, 'image/')) $mediaUrls[] = '/media/'.$asset->id;
        }
    }
}
