<?php

namespace App\Services;

use App\Models\{Article, ContentMedia, MediaAsset, Page};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\{DB, Storage};

class LegacyInlineMediaMigrator
{
    /** @return array{source:int, examined:int, migrated:int, existing:int, ignored:int, anomalies:int, errors:int, items:array} */
    public function reconcileAll(bool $apply): array
    {
        $counts = ['source' => 0, 'examined' => 0, 'migrated' => 0, 'existing' => 0, 'ignored' => 0, 'anomalies' => 0, 'errors' => 0, 'items' => []];

        foreach ([['post', Article::class], ['page', Page::class]] as [$type, $model]) {
            foreach ($model::query()->orderBy('legacy_wp_id')->cursor() as $content) {
                $this->reconcileContent($type, $content, $apply, $counts);
            }
        }

        return $counts;
    }

    private function reconcileContent(string $type, Model $content, bool $apply, array &$counts): void
    {
        $legacy = app(LegacyContentReader::class)->read($type, $content->legacy_wp_id)['post'];
        $html = app(ContentTransformer::class)->transform((string) $legacy->post_content)['html'];
        $prepared = app(ContentSanitizer::class)->sanitize($html, false)['html'];
        $images = $this->images($prepared);
        $resolved = [];

        foreach ($images as $position => $image) {
            $counts['source']++;
            $counts['examined']++;
            $item = ['content_type' => $type, 'legacy_wp_id' => (int) $content->legacy_wp_id, 'position' => $position + 1, 'legacy_url' => $image['src'], 'legacy_path' => $this->path($image['src']), 'legacy_attachment_id' => null, 'outcome' => null];
            try {
                $attachment = $this->attachmentFor($image['src']);
                $item['legacy_attachment_id'] = $attachment?->ID ? (int) $attachment->ID : null;
                $asset = $this->assetFor($attachment, $image['src'], $apply, $counts);
                if (! $asset) {
                    $counts['ignored']++;
                    $counts['anomalies']++;
                    $item['outcome'] = 'missing_physical_source';
                    $counts['items'][] = $item;
                    continue;
                }
                $resolved[$image['src']] = $asset;
                if ($apply) {
                    ContentMedia::updateOrCreate(
                        ['content_type' => $type, 'content_id' => $content->id, 'media_asset_id' => $asset->id, 'role' => 'inline'],
                        ['legacy_attachment_id' => $item['legacy_attachment_id'], 'legacy_path' => $item['legacy_path']],
                    );
                }
                $item['outcome'] = 'ready';
                $counts['items'][] = $item;
            } catch (\Throwable $error) {
                $counts['errors']++;
                $item['outcome'] = 'processing_error';
                $counts['items'][] = $item;
            }
        }

        if ($apply) {
            $rewritten = $this->rewrite($prepared, $resolved);
            $content->update(['content_html' => app(ContentSanitizer::class)->sanitize($rewritten)['html']]);
        }
    }

    private function assetFor(?object $attachment, string $url, bool $apply, array &$counts): ?MediaAsset
    {
        if ($attachment && ($existing = MediaAsset::where('legacy_attachment_id', $attachment->ID)->first())) {
            $counts['existing']++;
            return $existing;
        }

        $sourceUrl = $attachment?->guid ?: $url;
        try {
            $info = app(LegacyMediaReader::class)->inspect($sourceUrl);
        } catch (\Throwable) {
            return null;
        }
        if (! $apply) {
            $counts['migrated']++;
            return new MediaAsset(['id' => 0, 'width' => $info['width'], 'height' => $info['height']]);
        }

        $asset = MediaAsset::where('checksum', $info['checksum'])->first();
        if ($asset) {
            if ($attachment && ! $asset->legacy_attachment_id) $asset->update(['legacy_attachment_id' => $attachment->ID]);
            $counts['existing']++;
            return $asset;
        }
        $extension = strtolower(pathinfo($info['source'], PATHINFO_EXTENSION)) ?: 'bin';
        $path = "media/originals/{$info['checksum']}.{$extension}";
        $disk = Storage::disk(config('legacy-media.disk'));
        if (! $disk->exists($path)) $disk->put($path, file_get_contents($info['source']));
        $asset = MediaAsset::create([
            'legacy_attachment_id' => $attachment?->ID,
            'original_path' => $path,
            'mime' => $info['mime'],
            'width' => $info['width'],
            'height' => $info['height'],
            'bytes' => $info['bytes'],
            'checksum' => $info['checksum'],
            'alt_text' => $attachment?->post_excerpt ?: null,
            'status' => 'ready',
        ]);
        app(MediaVariantGenerator::class)->generate($asset);
        $counts['migrated']++;
        return $asset;
    }

    private function attachmentFor(string $url): ?object
    {
        $relative = ltrim((string) parse_url(html_entity_decode($url), PHP_URL_PATH), '/');
        $relative = preg_replace('#^wp-conten(?:t|u)/uploads/#', '', $relative);
        $original = preg_replace('/-\d+x\d+(?=\.[^.]+$)/', '', $relative);
        return DB::connection('legacy_wp')->table('postmeta as meta')
            ->join('posts as posts', 'posts.ID', '=', 'meta.post_id')
            ->where('posts.post_type', 'attachment')
            ->where('meta.meta_key', '_wp_attached_file')
            ->whereIn('meta.meta_value', array_values(array_unique([$relative, $original])))
            ->select('posts.ID', 'posts.guid', 'posts.post_excerpt')
            ->first();
    }

    /** @return list<array{src:string,tag:string}> */
    private function images(string $html): array
    {
        preg_match_all('/<img\b[^>]*\bsrc=["\']((?:https?:\/\/(?:www\.)?top-halal\.fr)?\/wp-conten(?:t|u)[^"\']*)["\'][^>]*>/i', $html, $matches, PREG_SET_ORDER);
        return array_map(fn (array $match) => ['src' => html_entity_decode($match[1]), 'tag' => $match[0]], $matches);
    }

    private function path(string $url): string
    {
        return (string) parse_url($url, PHP_URL_PATH);
    }

    /** @param array<string,MediaAsset> $resolved */
    private function rewrite(string $html, array $resolved): string
    {
        $html = preg_replace_callback('/<img\b[^>]*\bsrc=["\']((?:https?:\/\/(?:www\.)?top-halal\.fr)?\/wp-conten(?:t|u)[^"\']*)["\'][^>]*>/i', function (array $match) use ($resolved): string {
            $source = html_entity_decode($match[1]);
            if (! isset($resolved[$source])) return '';
            $asset = $resolved[$source];
            $alt = '';
            if (preg_match('/\balt=["\']([^"\']*)["\']/i', $match[0], $altMatch)) $alt = e(html_entity_decode($altMatch[1]));
            $legacyWidth = preg_match('/\bwidth=["\'](\d+)["\']/i', $match[0], $widthMatch) ? min((int) $widthMatch[1], $asset->width) : $asset->width;
            $legacyHeight = preg_match('/\bheight=["\'](\d+)["\']/i', $match[0], $heightMatch) ? (int) $heightMatch[1] : (int) round($asset->height * $legacyWidth / $asset->width);
            $srcset = $asset->variants()->orderBy('width')->get()->map(fn ($variant) => '/media/'.$asset->id.'/'.$variant->width.' '.$variant->width.'w')->implode(', ');
            return '<img class="content-inline-image" src="/media/'.$asset->id.'"'.($srcset ? ' srcset="'.$srcset.'" sizes="(max-width: '.$legacyWidth.'px) 100vw, '.$legacyWidth.'px"' : '').' width="'.$legacyWidth.'" height="'.$legacyHeight.'" loading="lazy" alt="'.$alt.'">';
        }, $html);
        $html = preg_replace_callback('/\bhref=["\']((?:https?:\/\/(?:www\.)?top-halal\.fr)?\/wp-conten(?:t|u)[^"\']*)["\']/i', function (array $match) use ($resolved): string {
            $source = html_entity_decode($match[1]);
            return isset($resolved[$source]) ? 'href="/media/'.$resolved[$source]->id.'"' : '';
        }, $html);
        return preg_replace('/<a\s*>\s*<\/a>/i', '', $html);
    }
}
