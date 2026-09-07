<?php

namespace App\Services;

use App\Models\Restaurant;
use Illuminate\Support\Facades\DB;

class RestaurantMediaOrderer
{
    /**
     * Move one ordinary restaurant photo one position and return its old and new positions.
     *
     * Specialty fallback thumbnails are deliberately excluded: they can never become a
     * restaurant cover or part of its public gallery.
     *
     * @return array{from: int, to: int}|null
     */
    public function move(Restaurant $restaurant, int $mediaId, string $direction): ?array
    {
        if (! in_array($direction, ['up', 'down'], true)) {
            return null;
        }

        return DB::transaction(function () use ($restaurant, $mediaId, $direction): ?array {
            $photos = $restaurant->media()
                ->where(function ($query): void {
                    $query->whereNull('role')->orWhere('role', '!=', 'fallback_thumbnail');
                })
                ->lockForUpdate()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->values();

            $from = $photos->search(fn ($media): bool => $media->id === $mediaId);
            if ($from === false) {
                return null;
            }

            $to = $direction === 'up' ? $from - 1 : $from + 1;
            if (! $photos->has($to)) {
                return null;
            }

            $ordered = $photos->all();
            [$ordered[$from], $ordered[$to]] = [$ordered[$to], $ordered[$from]];

            foreach ($ordered as $position => $media) {
                $media->update(['sort_order' => $position]);
            }

            return ['from' => $from, 'to' => $to];
        });
    }
}
