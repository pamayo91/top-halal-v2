<?php

namespace App\Services;

use App\Models\{Restaurant, RestaurantReview};
use Illuminate\Support\Facades\DB;

class LegacyReviewMigrator
{
    public function __construct(private readonly LegacyReviewReader $reader) {}

    public function inspect(object $post): array
    {
        $options = $post->legacy_options ?? $this->reader->options((int) $post->ID);
        $listingId = (int) ($options['listing_id'] ?? 0);
        $rating = $options['rating'] ?? null;
        $restaurant = $listingId ? Restaurant::where('legacy_wp_id', $listingId)->first() : null;
        $anomalies = [];
        if (! $restaurant) $anomalies[] = 'restaurant_not_migrated_or_invalid';
        if ($rating === null || $rating === '') $anomalies[] = 'missing_rating';
        elseif (! ctype_digit((string) $rating) || (int) $rating < 1 || (int) $rating > 5) $anomalies[] = 'invalid_rating';
        $author = $post->post_author ? DB::connection('legacy_wp')->table('users')->where('ID', $post->post_author)->value('display_name') : null;
        if ($post->post_author && ! $author) $anomalies[] = 'missing_legacy_author';
        if (preg_match('/<[^>]+>/', (string) $post->post_content)) $anomalies[] = 'legacy_html_converted_to_text';
        if (preg_match('/https?:\/\/|www\./i', (string) $post->post_content)) $anomalies[] = 'historical_url_preserved_as_text';
        return [
            'source' => ['legacy_wp_review_id' => (int) $post->ID, 'legacy_restaurant_id' => $listingId ?: null, 'legacy_user_id' => (int) $post->post_author ?: null, 'status' => $post->post_status, 'date_gmt' => $post->post_date_gmt, 'meta_keys' => $this->reader->metaKeys((int) $post->ID)],
            'target' => ['restaurant_id' => $restaurant?->id, 'legacy_wp_review_id' => (int) $post->ID, 'legacy_user_id' => (int) $post->post_author ?: null, 'author_name' => trim((string) $author) ?: 'Anonyme', 'author_email' => null, 'rating' => is_numeric($rating) ? (int) $rating : null, 'title' => trim((string) $post->post_title) ?: null, 'content' => trim(html_entity_decode(strip_tags((string) $post->post_content), ENT_QUOTES | ENT_HTML5, 'UTF-8')), 'status' => $post->post_status === 'publish' ? 'approved' : 'pending', 'approved_at' => $post->post_status === 'publish' && $post->post_date_gmt !== '0000-00-00 00:00:00' ? $post->post_date_gmt : null, 'created_at' => $this->historicalDate($post), 'updated_at' => $post->post_modified_gmt === '0000-00-00 00:00:00' ? now() : $post->post_modified_gmt],
            'anomalies' => array_values(array_unique($anomalies)),
        ];
    }

    private function historicalDate(object $post): string { foreach (['post_date_gmt', 'post_date', 'post_modified_gmt'] as $field) if (($post->{$field} ?? null) && $post->{$field} !== '0000-00-00 00:00:00') return $post->{$field}; return now()->toDateTimeString(); }

    public function persist(array $record): RestaurantReview
    {
        if ($record['target']['restaurant_id'] === null || $record['target']['rating'] === null || array_intersect($record['anomalies'], ['restaurant_not_migrated_or_invalid', 'missing_rating', 'invalid_rating']) !== []) throw new \RuntimeException('Review has unresolved migration anomalies.');
        return DB::transaction(fn () => RestaurantReview::updateOrCreate(['legacy_wp_review_id' => $record['target']['legacy_wp_review_id']], $record['target']));
    }
}
