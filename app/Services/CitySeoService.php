<?php

namespace App\Services;

use App\Models\{CitySeoPage, Setting};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class CitySeoService
{
    private const KEY = 'city-seo-stats-v3';

    public function __construct(private readonly CityPageResolver $cities) {}

    public function threshold(): int
    {
        return max(1, (int) (Setting::where('key', 'city_seo_minimum_restaurants')->value('value')['value'] ?? 5));
    }

    public function forget(): void
    {
        Cache::forget(self::KEY);
        $this->cities->forget();
    }

    /** @return Collection<int, object> */
    public function cities(): Collection
    {
        return collect(Cache::rememberForever(self::KEY, function (): array {
            $configs = CitySeoPage::query()->whereNotNull('city_code')->get()->keyBy('city_code');
            $threshold = $this->threshold();

            return $this->cities->cities()
                ->map(function (object $city) use ($configs, $threshold): array {
                    $config = $configs->get($city->city_code);
                    $state = $config?->state ?? 'auto';
                    $open = $state === 'forced_open' || ($state !== 'forced_closed' && $city->restaurants_count >= $threshold);

                    return [
                        ...get_object_vars($city),
                        'config' => $config?->getAttributes(),
                        'open' => $open,
                    ];
                })
                ->all();
        }))->map(function (array $city): object {
            $city['config'] = isset($city['config']) && $city['config'] !== null ? new CitySeoPage($city['config']) : null;

            return (object) $city;
        });
    }

    public function city(string $cityCode): ?object
    {
        return $this->cities()->firstWhere('city_code', $cityCode);
    }

    public function isOpen(string $cityCode): bool
    {
        return (bool) ($this->city($cityCode)?->open);
    }
}
