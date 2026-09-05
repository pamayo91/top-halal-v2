<?php

namespace App\Services;

use App\Models\Restaurant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/** Resolves public city slugs from the restaurant address source of truth. */
class CityPageResolver
{
    private const CACHE_KEY = 'public-city-page-names-v1';

    public function cityNameForSlug(string $slug): ?string
    {
        return $this->cityNames()->get($slug);
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** @return Collection<string, string> */
    private function cityNames(): Collection
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): Collection {
            return Restaurant::query()
                ->where('status', 'published')
                ->whereNotNull('city_name')
                ->where('city_name', '!=', '')
                ->distinct()
                ->orderBy('city_name')
                ->pluck('city_name')
                ->groupBy(fn (string $cityName): string => Str::slug($cityName))
                ->filter(fn (Collection $names): bool => $names->count() === 1)
                ->map(fn (Collection $names): string => $names->first());
        });
    }
}
