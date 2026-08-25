<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class LegacyReviewReader
{
    public function findMany(array $ids = [], array $restaurantIds = []): array
    {
        $query = DB::connection('legacy_wp')->table('posts')->where('post_type', 'lp-reviews');
        if ($ids !== []) $query->whereIn('ID', $ids);
        $posts = $query->orderBy('ID')->get();
        return $posts->filter(function ($post) use ($restaurantIds): bool {
            $options = $this->options((int) $post->ID);
            $post->legacy_options = $options;
            return $restaurantIds === [] || in_array((int) ($options['listing_id'] ?? 0), $restaurantIds, true);
        })->all();
    }

    public function options(int $reviewId): array
    {
        $value = DB::connection('legacy_wp')->table('postmeta')->where('post_id', $reviewId)->where('meta_key', 'lp_listingpro_options')->value('meta_value');
        $decoded = is_string($value) ? @unserialize($value, ['allowed_classes' => false]) : null;
        return is_array($decoded) ? $decoded : [];
    }

    public function metaKeys(int $reviewId): array
    {
        return DB::connection('legacy_wp')->table('postmeta')->where('post_id', $reviewId)->pluck('meta_key')->all();
    }
}
