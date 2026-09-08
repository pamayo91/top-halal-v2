<?php

namespace App\Services;

use App\Models\Restaurant;
use App\Models\RestaurantMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class RestaurantMediaManager
{
    /**
     * Attach newly uploaded V2 images to a restaurant's ordinary gallery.
     *
     * Existing media-library assets are deliberately retained when a gallery
     * relation is later removed: an asset can be used by another record.
     *
     * @param  array<int, UploadedFile>  $uploads
     * @return array{added: int, already_attached: int}
     */
    public function attachUploads(Restaurant $restaurant, array $uploads): array
    {
        return DB::transaction(function () use ($restaurant, $uploads): array {
            $nextPosition = (int) $restaurant->media()
                ->where(fn ($query) => $query->whereNull('role')->orWhere('role', '!=', 'fallback_thumbnail'))
                ->lockForUpdate()
                ->max('sort_order') + 1;
            $result = ['added' => 0, 'already_attached' => 0];

            foreach ($uploads as $upload) {
                $asset = app(MediaIngestor::class)->ingest($upload, $restaurant->name);
                $exists = $restaurant->media()->where('media_asset_id', $asset->id)->exists();

                if ($exists) {
                    $result['already_attached']++;
                    continue;
                }

                RestaurantMedia::create([
                    'restaurant_id' => $restaurant->id,
                    'media_asset_id' => $asset->id,
                    'sort_order' => $nextPosition++,
                    'status' => 'ready',
                    'role' => 'gallery',
                ]);
                $result['added']++;
            }

            return $result;
        });
    }

    public function detach(Restaurant $restaurant, int $mediaId): bool
    {
        return (bool) $restaurant->media()
            ->whereKey($mediaId)
            ->where(fn ($query) => $query->whereNull('role')->orWhere('role', '!=', 'fallback_thumbnail'))
            ->delete();
    }
}
