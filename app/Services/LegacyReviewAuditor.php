<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class LegacyReviewAuditor
{
    public function __construct(private readonly LegacyReviewReader $reader) {}

    public function audit(): array
    {
        $db = DB::connection('legacy_wp');
        $posts = $db->table('posts')->where('post_type', 'lp-reviews')->orderBy('ID')->get();
        $ratings = []; $valid = 0; $missing = 0; $users = 0; $guests = 0; $parents = 0; $html = 0; $urls = 0; $anomalies = [];
        foreach ($posts as $post) {
            $options = $this->reader->options((int) $post->ID);
            $listingId = (int) ($options['listing_id'] ?? 0);
            $rating = $options['rating'] ?? null;
            if ($rating !== null && $rating !== '') $ratings[(string) $rating] = ($ratings[(string) $rating] ?? 0) + 1;
            if ($listingId && $db->table('posts')->where('ID', $listingId)->where('post_type', 'listing')->exists()) $valid++; else { $missing++; $anomalies[] = ['legacy_wp_review_id' => (int) $post->ID, 'code' => 'missing_or_invalid_listing']; }
            $post->post_author ? $users++ : $guests++;
            if ($post->post_parent) $parents++;
            if (preg_match('/<[^>]+>/', (string) $post->post_content)) $html++;
            if (preg_match('/https?:\/\/|www\./i', (string) $post->post_content)) $urls++;
            if ($rating !== null && $rating !== '' && (!ctype_digit((string) $rating) || (int) $rating < 1 || (int) $rating > 5)) $anomalies[] = ['legacy_wp_review_id' => (int) $post->ID, 'code' => 'invalid_rating'];
        }
        return [
            'total' => $posts->count(), 'statuses' => $posts->countBy('post_status')->all(), 'rating_distribution' => $ratings,
            'without_rating' => $posts->count() - array_sum($ratings), 'valid_restaurant' => $valid, 'missing_restaurant' => $missing,
            'wordpress_authors' => $users, 'guests' => $guests, 'parent_or_reply' => $parents,
            'meta_keys' => $db->table('postmeta')->whereIn('post_id', $posts->pluck('ID'))->selectRaw('meta_key, count(*) as count')->groupBy('meta_key')->orderByDesc('count')->get()->map(fn ($row) => (array) $row)->all(),
            'signals' => ['html' => $html, 'url_like' => $urls], 'anomalies' => $anomalies,
        ];
    }
}
