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

    public function detachFallback(Restaurant $restaurant, int $mediaId): bool
    {
        return (bool) $restaurant->media()
            ->whereKey($mediaId)
            ->where('role', 'fallback_thumbnail')
            ->delete();
    }

    /** @param array<int, mixed> $mediaIds */
    public function detachMany(Restaurant $restaurant, array $mediaIds): void
    {
        $ids = collect($mediaIds)->filter(fn ($id) => is_numeric($id))->map(fn ($id) => (int) $id)->unique()->values();
        if ($ids->isEmpty()) return;
        $restaurant->media()->whereIn('id', $ids)->where(fn ($query) => $query->whereNull('role')->orWhere('role', '!=', 'fallback_thumbnail'))->delete();
    }

    /** @param array<int, mixed> $requestedIds */
    public function syncOrder(Restaurant $restaurant, array $requestedIds): void
    {
        $photos = $restaurant->media()->where(fn ($query) => $query->whereNull('role')->orWhere('role', '!=', 'fallback_thumbnail'))->lockForUpdate()->orderBy('sort_order')->orderBy('id')->get();
        $existing = $photos->pluck('id')->map(fn ($id) => (int) $id)->all();
        $requested = collect($requestedIds)->filter(fn ($id) => is_numeric($id))->map(fn ($id) => (int) $id)->unique()->values()->all();
        if ($requested !== [] && collect($requested)->diff($existing)->isNotEmpty()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['media_order' => 'Une photo à réordonner ne fait plus partie de cette fiche. Rechargez la page.']);
        }
        $ordered = collect($requested)->concat(collect($existing)->reject(fn (int $id) => in_array($id, $requested, true)))->values();
        foreach ($ordered as $position => $id) $photos->firstWhere('id', $id)?->update(['sort_order' => $position]);
    }
}
