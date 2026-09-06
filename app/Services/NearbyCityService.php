<?php

namespace App\Services;

use App\Models\{CityReferencePoint, Setting};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Builds the internal-linking candidates from local commune reference points.
 * Restaurant coordinates are deliberately never used as a substitute for a city centre.
 */
class NearbyCityService
{
    public const DEFAULT_RADIUS_KM = 30;
    public const DEFAULT_LIMIT = 15;
    public const MAX_RADIUS_KM = 250;
    public const MAX_LIMIT = 30;

    private const CACHE_VERSION_KEY = 'nearby-city-pages-version-v1';

    public function __construct(private readonly CitySeoService $cities) {}

    /** @return Collection<int, object> */
    public function nearbyFor(object $city): Collection
    {
        $radius = $this->radius();
        $limit = $this->limit();
        $version = (int) Cache::get(self::CACHE_VERSION_KEY, 1);
        $key = "nearby-city-pages-v1:{$version}:{$city->city_code}:{$radius}:{$limit}";

        return collect(Cache::remember($key, now()->addHours(12), function () use ($city, $radius, $limit): array {
            $reference = CityReferencePoint::query()->find($city->city_code);

            if ($reference === null) {
                return [];
            }

            $candidates = $this->cities->cities()
                ->filter(fn (object $candidate): bool => $candidate->open && $candidate->city_code !== $city->city_code)
                ->values();

            if ($candidates->isEmpty()) {
                return [];
            }

            $points = CityReferencePoint::query()
                ->whereIn('city_code', $candidates->pluck('city_code')->all())
                ->get()
                ->keyBy('city_code');

            return $candidates
                ->map(function (object $candidate) use ($points, $reference): ?array {
                    $point = $points->get($candidate->city_code);

                    if ($point === null) {
                        return null;
                    }

                    return [
                        'city_code' => $candidate->city_code,
                        'city_name' => $candidate->city_name,
                        'slug' => $candidate->slug,
                        'distance_km' => $this->distanceKm(
                            (float) $reference->latitude,
                            (float) $reference->longitude,
                            (float) $point->latitude,
                            (float) $point->longitude,
                        ),
                    ];
                })
                ->filter(fn (?array $candidate): bool => $candidate !== null && $candidate['distance_km'] <= $radius)
                ->sortBy('distance_km')
                ->take($limit)
                ->map(fn (array $candidate): array => [
                    'city_code' => $candidate['city_code'],
                    'city_name' => $candidate['city_name'],
                    'slug' => $candidate['slug'],
                ])
                ->values()
                ->all();
        }))->map(fn (array $city): object => (object) $city);
    }

    public function radius(): int
    {
        return $this->integerSetting('city_nearby_radius_km', self::DEFAULT_RADIUS_KM, self::MAX_RADIUS_KM);
    }

    public function limit(): int
    {
        return $this->integerSetting('city_nearby_maximum', self::DEFAULT_LIMIT, self::MAX_LIMIT);
    }

    /** Bump the generation so every radius, limit and SEO-state variant is invalidated together. */
    public function forget(): void
    {
        Cache::forever(self::CACHE_VERSION_KEY, ((int) Cache::get(self::CACHE_VERSION_KEY, 1)) + 1);
    }

    private function integerSetting(string $key, int $default, int $maximum): int
    {
        $value = Setting::query()->where('key', $key)->value('value');

        return min($maximum, max(1, (int) ($value['value'] ?? $default)));
    }

    private function distanceKm(float $latitudeA, float $longitudeA, float $latitudeB, float $longitudeB): float
    {
        $latitudeDelta = deg2rad($latitudeB - $latitudeA);
        $longitudeDelta = deg2rad($longitudeB - $longitudeA);
        $haversine = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($latitudeA)) * cos(deg2rad($latitudeB)) * sin($longitudeDelta / 2) ** 2;

        return 6371.0088 * 2 * asin(min(1, sqrt($haversine)));
    }
}
