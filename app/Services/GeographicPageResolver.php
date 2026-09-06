<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/** Builds only the administrative landing pages represented by published structured communes. */
class GeographicPageResolver
{
    private const CACHE_KEY = 'public-administrative-pages-v1';

    public function __construct(
        private readonly AdministrativeGeography $administrative,
        private readonly CityPageResolver $cities,
    ) {}

    /** @return Collection<int, object> */
    public function departments(): Collection
    {
        return $this->pages()['departments'];
    }

    /** @return Collection<int, object> */
    public function regions(): Collection
    {
        return $this->pages()['regions'];
    }

    public function departmentForSlug(string $slug): ?object
    {
        return $this->departments()->firstWhere('slug', $slug);
    }

    public function regionForSlug(string $slug): ?object
    {
        return $this->regions()->firstWhere('slug', $slug);
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function scopeDepartment(Builder $query, object $department): Builder
    {
        return $query->where('city_code', 'like', $department->code.'%');
    }

    public function scopeRegion(Builder $query, object $region): Builder
    {
        return $query->where(function (Builder $departments) use ($region): void {
            foreach ($region->department_codes as $departmentCode) {
                $departments->orWhere('city_code', 'like', $departmentCode.'%');
            }
        });
    }

    /** @return array{departments:Collection<int, object>,regions:Collection<int, object>} */
    private function pages(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            $departments = [];

            foreach ($this->cities->cities() as $city) {
                $code = $city->department['code'];
                $departments[$code] ??= [
                    ...$city->department,
                    'region' => $city->region,
                    'restaurants_count' => 0,
                ];
                $departments[$code]['restaurants_count'] += $city->restaurants_count;
            }

            $regions = [];
            foreach ($departments as $department) {
                $code = $department['region']['code'];
                $regions[$code] ??= [
                    ...$department['region'],
                    'department_codes' => [],
                    'restaurants_count' => 0,
                ];
                $regions[$code]['department_codes'][] = $department['code'];
                $regions[$code]['restaurants_count'] += $department['restaurants_count'];
            }

            return [
                'departments' => collect($departments)->map(fn (array $department): object => (object) $department)->sortBy('name')->values(),
                'regions' => collect($regions)->map(function (array $region): object {
                    $region['department_codes'] = array_values($region['department_codes']);

                    return (object) $region;
                })->sortBy('name')->values(),
            ];
        });
    }
}
