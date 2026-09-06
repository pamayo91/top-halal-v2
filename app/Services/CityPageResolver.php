<?php

namespace App\Services;

use App\Models\Restaurant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use LogicException;

/** Resolves public city pages from the structured city code, never legacy Geography or free text. */
class CityPageResolver
{
    private const CACHE_KEY = 'public-city-pages-v3';

    public function __construct(private readonly AdministrativeGeography $administrative) {}

    public function cityForSlug(string $slug): ?object
    {
        return $this->cities()->firstWhere('slug', $slug);
    }

    /** @return Collection<int, object> */
    public function ambiguityForSlug(string $slug): Collection
    {
        $cities = $this->cities()->filter(fn (object $city): bool => $city->base_slug === $slug && $city->is_ambiguous);

        return $cities->count() > 1
            ? $cities->sortBy(['region.name', 'department.name', 'city_name'])
            : collect();
    }

    public function cityForCode(string $cityCode): ?object
    {
        $canonicalCode = $this->administrative->canonicalCityCode($cityCode);

        return $canonicalCode === null ? null : $this->cities()->firstWhere('city_code', $canonicalCode);
    }

    public function cityNameForSlug(string $slug): ?string
    {
        return $this->cityForSlug($slug)?->city_name;
    }

    /** @return Collection<int, object> */
    public function cities(): Collection
    {
        return collect(Cache::rememberForever(self::CACHE_KEY, function (): array {
            $cities = [];

            Restaurant::query()
                ->where('status', 'published')
                ->whereNotNull('city_name')
                ->where('city_name', '!=', '')
                ->whereNotNull('city_code')
                ->where('city_code', '!=', '')
                ->selectRaw('city_name, city_code, COUNT(*) as restaurants_count')
                ->groupBy('city_name', 'city_code')
                ->orderBy('city_name')
                ->orderBy('city_code')
                ->get()
                ->each(function (object $row) use (&$cities): void {
                    $area = $this->administrative->resolve((string) $row->city_code);
                    if ($area === null) {
                        return;
                    }

                    $code = $area['city_code'];
                    $cities[$code] ??= [
                        'city_code' => $code,
                        'city_names' => [],
                        'source_city_codes' => [],
                        'restaurants_count' => 0,
                        'department' => $area['department'],
                        'region' => $area['region'],
                    ];
                    $cities[$code]['city_names'][$row->city_name] = ($cities[$code]['city_names'][$row->city_name] ?? 0) + (int) $row->restaurants_count;
                    $cities[$code]['source_city_codes'][] = (string) $row->city_code;
                    $cities[$code]['restaurants_count'] += (int) $row->restaurants_count;
                });

            foreach ($cities as &$city) {
                arsort($city['city_names'], SORT_NUMERIC);
                $city['city_name'] = array_key_first($city['city_names']);
                $city['base_slug'] = Str::slug($city['city_name']);
                $city['slug'] = $city['base_slug'];
                $city['is_ambiguous'] = false;
                $city['source_city_codes'] = array_values(array_unique($city['source_city_codes']));
                unset($city['city_names']);
            }
            unset($city);

            foreach (collect($cities)->groupBy('base_slug') as $baseSlug => $sameNamedCities) {
                if ($sameNamedCities->count() < 2) {
                    continue;
                }

                foreach ($sameNamedCities as $cityCode => $city) {
                    $cities[$cityCode]['is_ambiguous'] = true;
                    $cities[$cityCode]['slug'] = $baseSlug.'-'.$city['department']['code'];
                }
            }

            $collisions = collect($cities)->groupBy('slug')->filter(fn (Collection $sameSlug): bool => $sameSlug->count() > 1);
            if ($collisions->isNotEmpty()) {
                throw new LogicException('Two distinct structured communes share the same public city slug.');
            }

            return collect($cities)->sortBy(['city_name', 'city_code'])->values()->all();
        }))->map(fn (array $city): object => (object) $city);
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
