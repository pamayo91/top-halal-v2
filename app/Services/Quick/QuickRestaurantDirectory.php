<?php

namespace App\Services\Quick;

use App\Models\Restaurant;
use App\Services\CityPageResolver;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reads the V2 namespace materialised by restaurants:sync-quick.
 *
 * Quick records retain their public slugs (including audited historical
 * matches), so the namespace is the durable V2 identifier here. Do not
 * replace this with a name search: names are editorial data, not provenance.
 */
class QuickRestaurantDirectory
{
    public function __construct(private readonly CityPageResolver $cities) {}

    /** @return Builder<Restaurant> */
    public function published(): Builder
    {
        return Restaurant::query()
            ->where('status', 'published')
            ->where('slug', 'like', 'quick-%');
    }

    /**
     * @return array{points: list<array{latitude: float, longitude: float, name: string, city: string, url: string}>, cities: list<array{name: string, count: int, url: string}>}|null
     */
    public function mapData(): ?array
    {
        $restaurants = $this->published()
            ->get(['id', 'name', 'slug', 'latitude', 'longitude', 'city_code']);

        if ($restaurants->isEmpty()) {
            return null;
        }

        $cities = [];
        $points = [];

        foreach ($restaurants as $restaurant) {
            $city = filled($restaurant->city_code)
                ? $this->cities->cityForCode((string) $restaurant->city_code)
                : null;

            if ($city !== null) {
                $code = $city->city_code;
                $cities[$code] ??= [
                    'name' => $city->city_name,
                    'count' => 0,
                    'url' => route('cities.show', $city->slug),
                ];
                $cities[$code]['count']++;
            }

            $latitude = $restaurant->latitude === null ? null : (float) $restaurant->latitude;
            $longitude = $restaurant->longitude === null ? null : (float) $restaurant->longitude;
            if ($latitude === null || $longitude === null || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
                continue;
            }

            $points[] = [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'name' => $restaurant->name,
                'city' => $city?->city_name ?? '',
                'url' => route('restaurants.show', $restaurant->slug),
            ];
        }

        $cities = array_values($cities);
        usort($cities, static fn (array $left, array $right): int => $right['count'] <=> $left['count'] ?: strcoll($left['name'], $right['name']));

        return ['points' => $points, 'cities' => array_slice($cities, 0, 5)];
    }
}
