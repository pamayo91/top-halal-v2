<?php

namespace App\Console\Commands;

use App\Models\MediaAsset;
use App\Models\MediaVariant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB, File, Storage};

class RemoveRestaurantVideosCommand extends Command
{
    protected $signature = 'data:remove-restaurant-videos
        {--apply : Delete video-to-restaurant relations and normalize the remaining gallery order}
        {--purge-orphaned-assets : With --apply, delete orphaned V2 video assets and their unshared storage files}
        {--out=docs/generated/restaurant-video-removal : Report path without extension}';

    protected $description = 'Removes videos from restaurant galleries using V2 data and storage only.';

    public function handle(): int
    {
        if ($this->option('purge-orphaned-assets') && ! $this->option('apply')) {
            $this->error('--purge-orphaned-assets requires --apply.');

            return self::FAILURE;
        }

        $before = $this->audit();
        $result = [
            'relations_removed' => 0,
            'restaurants_reordered' => 0,
            'restaurants_without_image' => [],
            'assets_purged' => 0,
            'assets_retained_as_shared' => [],
            'storage_paths_deleted' => 0,
            'storage_paths_retained_as_shared' => [],
            'storage_delete_failures' => [],
            'samples' => [],
        ];

        if ($this->option('apply')) {
            $pathsToDelete = [];

            DB::transaction(function () use (&$result, &$pathsToDelete): void {
                $videos = $this->videos()->lockForUpdate()->get();
                $restaurantIds = [];

                foreach ($videos as $video) {
                    $relations = DB::table('restaurant_media')->where('media_asset_id', $video->id)->lockForUpdate()->get();
                    foreach ($relations as $relation) {
                        $restaurantIds[] = $relation->restaurant_id;
                        $result['relations_removed'] += DB::table('restaurant_media')->where('id', $relation->id)->delete();
                        $this->sample($result['samples'], ['restaurant_media_id' => $relation->id, 'restaurant_id' => $relation->restaurant_id, 'media_asset_id' => $video->id, 'outcome' => 'relation_removed']);
                    }

                    if ($this->option('purge-orphaned-assets')) {
                        $references = $this->assetReferences($video->id);
                        if ($references['restaurant_media'] === 0 && $references['content_media'] === 0) {
                            $pathsToDelete = array_merge($pathsToDelete, $this->unsharedPaths($video));
                            $video->delete();
                            $result['assets_purged']++;
                            $this->sample($result['samples'], ['media_asset_id' => $video->id, 'outcome' => 'asset_purged']);
                        } else {
                            $result['assets_retained_as_shared'][] = ['media_asset_id' => $video->id, 'references' => $references];
                        }
                    }
                }

                foreach (array_values(array_unique($restaurantIds)) as $restaurantId) {
                    $this->normalizeRestaurantOrder($restaurantId);
                    $result['restaurants_reordered']++;

                    if (! $this->restaurantHasImage($restaurantId)) {
                        $result['restaurants_without_image'][] = $restaurantId;
                    }
                }
            });

            foreach (array_unique($pathsToDelete) as $path) {
                if (Storage::disk(config('legacy-media.disk'))->exists($path)) {
                    if (Storage::disk(config('legacy-media.disk'))->delete($path)) {
                        $result['storage_paths_deleted']++;
                    } else {
                        $result['storage_delete_failures'][] = $path;
                    }
                }
            }
        } else {
            foreach ($this->videos()->get() as $video) {
                $this->sample($result['samples'], ['media_asset_id' => $video->id, 'outcome' => 'would_remove']);
            }
            $result['restaurants_without_image'] = $this->affectedRestaurantsWithoutReplacement();
        }

        $after = $this->audit();
        $report = [
            'generated_at' => now()->toIso8601String(),
            'mode' => $this->option('apply') ? 'apply' : 'dry-run',
            'purge_orphaned_assets' => (bool) $this->option('purge-orphaned-assets'),
            'source' => 'V2 database and V2 media storage only; no legacy connection is read.',
            'before' => $before,
            'result' => $result,
            'after' => $after,
        ];
        $this->writeReport($report);

        $this->info(sprintf('Video relations: %d; removed: %d; orphaned assets purged: %d; restaurants without an image: %d.', $before['video_relations'], $result['relations_removed'], $result['assets_purged'], count($result['restaurants_without_image'])));

        return self::SUCCESS;
    }

    private function videos()
    {
        return MediaAsset::query()->where('mime', 'like', 'video/%')->orderBy('id');
    }

    /** @return array<string, int> */
    private function audit(): array
    {
        return [
            'restaurant_media_total' => DB::table('restaurant_media')->count(),
            'video_assets' => $this->videos()->count(),
            'video_relations' => DB::table('restaurant_media')->join('media_assets', 'media_assets.id', '=', 'restaurant_media.media_asset_id')->where('media_assets.mime', 'like', 'video/%')->count(),
            'affected_restaurants' => DB::table('restaurant_media')->join('media_assets', 'media_assets.id', '=', 'restaurant_media.media_asset_id')->where('media_assets.mime', 'like', 'video/%')->distinct()->count('restaurant_media.restaurant_id'),
        ];
    }

    /** @return array<string, int> */
    private function assetReferences(int $assetId): array
    {
        return [
            'restaurant_media' => DB::table('restaurant_media')->where('media_asset_id', $assetId)->count(),
            'content_media' => DB::table('content_media')->where('media_asset_id', $assetId)->count(),
        ];
    }

    /** @return array<int, string> */
    private function unsharedPaths(MediaAsset $asset): array
    {
        $paths = [];
        if (! MediaAsset::query()->where('original_path', $asset->original_path)->whereKeyNot($asset->id)->exists()) {
            $paths[] = $asset->original_path;
        }

        foreach ($asset->variants as $variant) {
            if (! MediaVariant::query()->where('path', $variant->path)->where('media_asset_id', '!=', $asset->id)->exists()) {
                $paths[] = $variant->path;
            }
        }

        return $paths;
    }

    private function normalizeRestaurantOrder(int $restaurantId): void
    {
        DB::table('restaurant_media')->where('restaurant_id', $restaurantId)->orderBy('sort_order')->orderBy('id')->get()->each(function (object $media, int $order): void {
            if ((int) $media->sort_order !== $order) {
                DB::table('restaurant_media')->where('id', $media->id)->update(['sort_order' => $order, 'updated_at' => now()]);
            }
        });
    }

    private function restaurantHasImage(int $restaurantId): bool
    {
        return DB::table('restaurant_media')->join('media_assets', 'media_assets.id', '=', 'restaurant_media.media_asset_id')->where('restaurant_media.restaurant_id', $restaurantId)->whereIn('media_assets.mime', MediaAsset::RESTAURANT_IMAGE_MIMES)->exists();
    }

    /** @return array<int, int> */
    private function affectedRestaurantsWithoutReplacement(): array
    {
        return DB::table('restaurant_media as video_media')
            ->join('media_assets as video_assets', 'video_assets.id', '=', 'video_media.media_asset_id')
            ->where('video_assets.mime', 'like', 'video/%')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')->from('restaurant_media as image_media')->join('media_assets as image_assets', 'image_assets.id', '=', 'image_media.media_asset_id')->whereColumn('image_media.restaurant_id', 'video_media.restaurant_id')->whereIn('image_assets.mime', MediaAsset::RESTAURANT_IMAGE_MIMES);
            })
            ->distinct()->orderBy('video_media.restaurant_id')->pluck('video_media.restaurant_id')->map(fn ($id) => (int) $id)->all();
    }

    /** @param array<int, array<string, mixed>> $samples */
    private function sample(array &$samples, array $item): void
    {
        if (count($samples) < 20) {
            $samples[] = $item;
        }
    }

    /** @param array<string, mixed> $report */
    private function writeReport(array $report): void
    {
        $base = base_path((string) $this->option('out'));
        File::ensureDirectoryExists(dirname($base));
        File::put($base.'.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        File::put($base.'.md', implode("\n", [
            '# Suppression des vidéos des fiches restaurant',
            '',
            'Généré le : `'.$report['generated_at'].'`',
            'Mode : `'.$report['mode'].'`',
            'Purge des assets orphelins : `'.($report['purge_orphaned_assets'] ? 'oui' : 'non').'`.',
            '',
            'Opération limitée à V2 : aucune connexion legacy n’est lue.',
            '',
            '## Résultat',
            '- Relations vidéo trouvées : '.$report['before']['video_relations'].'.',
            '- Relations supprimées : '.$report['result']['relations_removed'].'.',
            '- Fiches réordonnées : '.$report['result']['restaurants_reordered'].'.',
            '- Assets vidéo orphelins supprimés : '.$report['result']['assets_purged'].'.',
            '- Chemins de stockage supprimés : '.$report['result']['storage_paths_deleted'].'.',
            '- Fiches sans image de remplacement : '.count($report['result']['restaurants_without_image']).'.',
            '- Relations vidéo restantes : '.$report['after']['video_relations'].'.',
        ])."\n");
    }
}
