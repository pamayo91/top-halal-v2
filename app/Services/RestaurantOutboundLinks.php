<?php

namespace App\Services;

use App\Models\Restaurant;
use App\Models\RestaurantOutboundLink;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;

class RestaurantOutboundLinks
{
    public const FIELDS = [
        'website_url' => 'Site web',
        'instagram_url' => 'Instagram',
        'facebook_url' => 'Facebook',
        'tiktok_url' => 'TikTok',
    ];

    public static function validDestination(?string $url): bool
    {
        if (! is_string($url) || $url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        return in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true);
    }

    public function formData(Restaurant $restaurant): array
    {
        $links = $restaurant->outboundLinks()->get()->keyBy('label');

        return collect(self::FIELDS)->mapWithKeys(fn (string $label, string $field) => [
            $field => $links->get($label)?->destination_url,
        ])->all();
    }

    /** @param array<string, mixed> $data */
    public function sync(Restaurant $restaurant, array $data): void
    {
        foreach (self::FIELDS as $field => $label) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $link = $restaurant->outboundLinks()->where('label', $label)->first();
            $url = filled($data[$field] ?? null) ? trim((string) $data[$field]) : null;

            if ($url === null) {
                $link?->delete();
                continue;
            }

            $attributes = ['destination_url' => $url, 'is_active' => $restaurant->status === 'published'];
            if ($link) {
                $link->update($attributes);
                continue;
            }

            $restaurant->outboundLinks()->create([
                ...$attributes,
                'token' => Str::random(40),
                'label' => $label,
            ]);
        }
    }

    public function activateForPublishedRestaurant(Restaurant $restaurant): void
    {
        $restaurant->outboundLinks()->whereIn('label', array_values(self::FIELDS))->each(function (RestaurantOutboundLink $link): void {
            if (self::validDestination($link->destination_url)) {
                $link->update(['is_active' => true]);
            }
        });
    }

    /** @param iterable<RestaurantOutboundLink> $links */
    public function publicLinks(iterable $links): Collection
    {
        return collect($links)
            ->filter(fn (RestaurantOutboundLink $link): bool => $link->is_active
                && in_array($link->label, self::FIELDS, true)
                && self::validDestination($link->destination_url))
            ->sortBy(fn (RestaurantOutboundLink $link): int => array_search($link->label, array_values(self::FIELDS), true))
            ->values();
    }
}
