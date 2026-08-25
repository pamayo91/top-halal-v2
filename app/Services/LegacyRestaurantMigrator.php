<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Feature;
use App\Models\Location;
use App\Models\Restaurant;
use App\Models\RestaurantMedia;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class LegacyRestaurantMigrator
{
    public function __construct(private readonly string $connection = 'legacy_wp') {}

    /** @return array<int, array<int, string>> */
    public function sample(int $limit): array
    {
        $prefix = $this->prefix();
        $posts = $prefix.'posts';
        $meta = $prefix.'postmeta';
        $terms = $prefix.'term_relationships';
        $base = fn () => DB::connection($this->connection)->table($posts.' as post')->where('post.post_type', 'listing');
        $choices = [
            'published_classic' => $base()->where('post.post_status', 'publish')->orderBy('post.ID')->value('post.ID'),
            'multiple_categories' => $this->termCandidate($terms, 'listing-category', 2),
            'multiple_features' => $this->termCandidate($terms, 'features', 2),
            'gallery' => $this->metaCandidate($meta, 'gallery_image_ids'),
            'hours' => $this->metaCandidateRegex($meta, 'monday|lundi|opening|hour|timing'),
            'gps' => $this->metaCandidateRegex($meta, 'latitude|longitude|\\blat\\b|\\blng\\b'),
            'incomplete_location' => $base()->where('post.post_status', 'publish')->whereNotExists(fn ($query) => $query->selectRaw('1')->from($meta)->whereColumn('post_id', 'post.ID')->whereIn('meta_key', ['fave_property_location', 'latitude']))->orderBy('post.ID')->value('post.ID'),
            'claimed' => $this->metaCandidate($meta, 'claimed'),
            'pending' => $base()->where('post.post_status', 'pending')->orderBy('post.ID')->value('post.ID'),
            'unusual_meta' => $this->metaCandidate($meta, 'lp_listingpro_options_fields'),
        ];

        $selected = [];
        foreach ($choices as $reason => $id) {
            if ($id !== null) $selected[(int) $id][] = $reason;
        }

        foreach ($base()->where('post.post_status', 'publish')->orderBy('post.ID')->pluck('post.ID') as $id) {
            if (count($selected) >= $limit) break;
            if (! array_key_exists((int) $id, $selected)) $selected[(int) $id] = ['published_fallback_'.count($selected)];
        }

        return array_slice($selected, 0, $limit, true);
    }

    /** @return array<string, mixed> */
    public function inspect(int $legacyId, string|array|null $selectionReason = null): array
    {
        $connection = DB::connection($this->connection);
        $prefix = $this->prefix();
        $post = $connection->table($prefix.'posts')->where('ID', $legacyId)->where('post_type', 'listing')->first();
        if ($post === null) throw new \RuntimeException("Legacy listing $legacyId was not found.");

        $meta = $connection->table($prefix.'postmeta')->where('post_id', $legacyId)->pluck('meta_value', 'meta_key')->all();
        $terms = $connection->table($prefix.'term_relationships as relationship')
            ->join($prefix.'term_taxonomy as taxonomy', 'taxonomy.term_taxonomy_id', '=', 'relationship.term_taxonomy_id')
            ->join($prefix.'terms as term', 'term.term_id', '=', 'taxonomy.term_id')
            ->where('relationship.object_id', $legacyId)
            ->whereIn('taxonomy.taxonomy', ['listing-category', 'features', 'location'])
            ->select('term.term_id', 'term.name', 'term.slug', 'taxonomy.taxonomy', 'taxonomy.parent')
            ->orderBy('taxonomy.taxonomy')->orderBy('term.term_id')->get();
        $attachments = $this->attachments($connection, $prefix, $meta['gallery_image_ids'] ?? '');
        $flat = $this->flattenMeta($meta);
        $anomalies = [];
        $description = $this->plainDescription((string) $post->post_content, $anomalies);
        $slug = $this->slug((string) $post->post_name, (string) $post->post_title, $legacyId, $anomalies);
        $coordinates = $this->coordinates($flat);
        if ($coordinates['latitude'] === null || $coordinates['longitude'] === null) $anomalies[] = 'missing_or_invalid_coordinates';
        if ($terms->where('taxonomy', 'location')->isEmpty()) $anomalies[] = 'no_legacy_location_term';
        if ($attachments === []) $anomalies[] = 'no_gallery_media';

        $hours = $this->hours($flat);
        if ($hours === []) $anomalies[] = 'no_recognized_opening_hours';

        return [
            'source' => [
                'legacy_wp_id' => (int) $post->ID,
                'selection_reason' => $selectionReason,
                'status' => (string) $post->post_status,
                'slug' => (string) $post->post_name,
                'title' => (string) $post->post_title,
                'meta_keys' => array_keys($meta),
                'term_counts' => $terms->countBy('taxonomy')->all(),
                'gallery_attachment_ids' => array_column($attachments, 'legacy_attachment_id'),
            ],
            'target' => [
                'restaurant' => [
                    'legacy_wp_id' => (int) $post->ID,
                    'legacy_author_id' => (int) $post->post_author ?: null,
                    'name' => trim((string) $post->post_title),
                    'slug' => $slug,
                    'description' => $description,
                    'status' => $this->status((string) $post->post_status),
                    'is_claimed' => $this->truthy($meta['claimed'] ?? null),
                    'address' => $this->first($flat, ['address', 'map_address', 'fave_property_map_address']),
                    'postal_code' => $this->first($flat, ['postcode', 'postal_code', 'zip']),
                    'city_name' => $this->first($flat, ['city', 'town']),
                    'phone' => $this->first($flat, ['telephone', 'phone', 'mobile']),
                    'contact_email' => $this->first($flat, ['email']),
                    ...$coordinates,
                    'legacy_published_at' => $post->post_date_gmt !== '0000-00-00 00:00:00' ? $post->post_date_gmt : null,
                    'legacy_modified_at' => $post->post_modified_gmt !== '0000-00-00 00:00:00' ? $post->post_modified_gmt : null,
                ],
                'terms' => $terms->map(fn ($term) => [
                    'legacy_term_id' => (int) $term->term_id,
                    'name' => (string) $term->name,
                    'slug' => (string) $term->slug,
                    'taxonomy' => (string) $term->taxonomy,
                    'legacy_parent_term_id' => (int) $term->parent ?: null,
                ])->all(),
                'hours' => $hours,
                'media' => $attachments,
            ],
            'anomalies' => array_values(array_unique($anomalies)),
            'destination' => ['connection' => config('database.default'), 'restaurant_legacy_wp_id' => (int) $post->ID],
        ];
    }

    /** @param array<string, mixed> $record */
    public function persist(array $record): Restaurant
    {
        return DB::transaction(function () use ($record): Restaurant {
            $attributes = $record['target']['restaurant'];
            if ($attributes['name'] === '') throw new \RuntimeException('Restaurant name is empty.');
            $restaurant = Restaurant::updateOrCreate(['legacy_wp_id' => $attributes['legacy_wp_id']], $attributes);
            $terms = collect($record['target']['terms']);
            $restaurant->categories()->sync($terms->where('taxonomy', 'listing-category')->map(fn ($term) => Category::updateOrCreate(['legacy_term_id' => $term['legacy_term_id']], Arr::only($term, ['name', 'slug']))->id));
            $restaurant->features()->sync($terms->where('taxonomy', 'features')->map(fn ($term) => Feature::updateOrCreate(['legacy_term_id' => $term['legacy_term_id']], Arr::only($term, ['name', 'slug']))->id));
            $restaurant->locations()->sync($terms->where('taxonomy', 'location')->map(fn ($term) => $this->persistLocation($term, $terms))->filter());
            $restaurant->openingHours()->delete();
            foreach ($record['target']['hours'] as $hour) $restaurant->openingHours()->create($hour);
            $attachmentIds = collect($record['target']['media'])->pluck('legacy_attachment_id')->all();
            $restaurant->media()->whereNotIn('legacy_attachment_id', $attachmentIds ?: [0])->delete();
            foreach ($record['target']['media'] as $media) RestaurantMedia::updateOrCreate(['restaurant_id' => $restaurant->id, 'legacy_attachment_id' => $media['legacy_attachment_id']], $media);
            return $restaurant;
        });
    }

    private function termCandidate(string $relationships, string $taxonomy, int $minimum): ?int
    {
        return DB::connection($this->connection)->table($relationships.' as relationship')->join($this->prefix().'term_taxonomy as taxonomy', 'taxonomy.term_taxonomy_id', '=', 'relationship.term_taxonomy_id')->join($this->prefix().'posts as post', 'post.ID', '=', 'relationship.object_id')->where('post.post_type', 'listing')->where('post.post_status', 'publish')->where('taxonomy.taxonomy', $taxonomy)->groupBy('relationship.object_id')->havingRaw('COUNT(*) >= ?', [$minimum])->orderBy('relationship.object_id')->value('relationship.object_id');
    }

    private function metaCandidate(string $meta, string $key): ?int
    {
        return DB::connection($this->connection)->table($meta.' as meta')->join($this->prefix().'posts as post', 'post.ID', '=', 'meta.post_id')->where('post.post_type', 'listing')->where('meta.meta_key', $key)->where('meta.meta_value', '!=', '')->orderBy('meta.post_id')->value('meta.post_id');
    }
    private function metaCandidateRegex(string $meta, string $pattern): ?int
    {
        return DB::connection($this->connection)->table($meta.' as meta')->join($this->prefix().'posts as post', 'post.ID', '=', 'meta.post_id')->where('post.post_type', 'listing')->where('meta.meta_value', 'REGEXP', $pattern)->orderBy('meta.post_id')->value('meta.post_id');
    }
    private function prefix(): string { return ''; }

    /** @return array<int, array<string, mixed>> */
    private function attachments(ConnectionInterface $connection, string $prefix, string $value): array
    {
        $ids = collect(preg_split('/[^0-9]+/', $value) ?: [])->filter()->map(fn ($id) => (int) $id)->unique()->values();
        if ($ids->isEmpty()) return [];
        return $connection->table($prefix.'posts')->whereIn('ID', $ids)->where('post_type', 'attachment')->select('ID', 'guid', 'post_excerpt')->get()->map(fn ($attachment, $index) => ['legacy_attachment_id' => (int) $attachment->ID, 'legacy_path' => parse_url((string) $attachment->guid, PHP_URL_PATH), 'alt_text' => Str::limit(strip_tags((string) $attachment->post_excerpt), 255, ''), 'sort_order' => $index, 'status' => 'pending'])->all();
    }

    /** @param array<string, string> $meta @return array<string, string> */
    private function flattenMeta(array $meta): array
    {
        $flat = [];
        foreach ($meta as $key => $value) foreach ($this->flattenValue($this->decode($value), (string) $key) as $path => $leaf) $flat[$path] = $leaf;
        return $flat;
    }

    /** @return array<string, string> */
    private function flattenValue(mixed $value, string $path): array
    {
        if (! is_array($value)) return [$path => is_scalar($value) ? trim((string) $value) : ''];
        $result = [];
        foreach ($value as $key => $child) $result += $this->flattenValue($child, $path.'.'.$key);
        return $result;
    }

    private function decode(mixed $value): mixed
    {
        if (! is_string($value)) return $value;
        $json = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE) return $json;
        $decoded = @unserialize($value, ['allowed_classes' => false]);
        return $decoded === false && $value !== 'b:0;' ? $value : $decoded;
    }

    /** @param array<string, string> $flat @param array<int, string> $needles */
    private function first(array $flat, array $needles): ?string
    {
        foreach ($needles as $needle) foreach ($flat as $path => $value) if ($value !== '' && str_contains(Str::lower($path), $needle)) return Str::limit($value, 255, '');
        return null;
    }

    /** @param array<string, string> $flat @return array{latitude: ?string, longitude: ?string} */
    private function coordinates(array $flat): array
    {
        $latitude = $this->first($flat, ['latitude', '.lat']);
        $longitude = $this->first($flat, ['longitude', '.lng', '.lon']);
        return ['latitude' => $this->coordinate($latitude, -90, 90), 'longitude' => $this->coordinate($longitude, -180, 180)];
    }

    private function coordinate(?string $value, float $min, float $max): ?string { return $value !== null && is_numeric($value) && (float) $value >= $min && (float) $value <= $max ? $value : null; }
    private function truthy(mixed $value): bool { return in_array(Str::lower((string) $value), ['1', 'true', 'yes', 'on'], true); }
    private function status(string $status): string { return match ($status) { 'publish' => 'published', 'pending' => 'pending', 'reported' => 'reported', default => 'draft' }; }

    /** @param array<int, string> $anomalies */
    private function plainDescription(string $content, array &$anomalies): ?string
    {
        if (str_contains($content, '[vc_')) $anomalies[] = 'visual_composer_shortcode_stripped';
        $text = trim(preg_replace('/\[[^\]]+\]/', '', strip_tags($content)) ?? '');
        return $text === '' ? null : $text;
    }

    /** @param array<int, string> $anomalies */
    private function slug(string $legacySlug, string $title, int $id, array &$anomalies): string
    {
        $slug = Str::slug($legacySlug !== '' ? $legacySlug : $title);
        if ($slug === '') { $slug = "restaurant-$id"; $anomalies[] = 'missing_legacy_slug'; }
        return $slug;
    }

    /** @param array<string, string> $flat @return array<int, array<string, mixed>> */
    private function hours(array $flat): array
    {
        $hours = [];
        foreach ($flat as $path => $value) {
            if ($value === '' || ! preg_match('/(?:hour|opening|timing|monday|tuesday|wednesday|thursday|friday|saturday|sunday|lundi|mardi|mercredi|jeudi|vendredi|samedi|dimanche)/i', $path)) continue;
            preg_match_all('/\b([01]?\d|2[0-3]):[0-5]\d\b/', $value, $matches);
            $times = $matches[0] ?? [];
            $hours[] = ['day' => $this->day($path), 'opens_at' => $times[0] ?? null, 'closes_at' => $times[1] ?? null, 'is_closed' => preg_match('/closed|ferme/i', $value) === 1, 'legacy_key' => Str::limit($path, 255, ''), 'legacy_value' => Str::limit($value, 2000, '')];
        }
        return $hours;
    }

    private function day(string $path): ?string { return preg_match('/(monday|lundi|tuesday|mardi|wednesday|mercredi|thursday|jeudi|friday|vendredi|saturday|samedi|sunday|dimanche)/i', $path, $match) ? Str::lower($match[1]) : null; }

    /** @param array<string, mixed> $term */
    private function persistLocation(array $term, $terms): int
    {
        $parentId = null;
        if ($term['legacy_parent_term_id']) {
            $parent = $terms->firstWhere('legacy_term_id', $term['legacy_parent_term_id']);
            if ($parent) $parentId = $this->persistLocation($parent, $terms);
        }
        return Location::updateOrCreate(['legacy_term_id' => $term['legacy_term_id']], ['name' => $term['name'], 'slug' => $term['slug'], 'parent_id' => $parentId])->id;
    }
}
