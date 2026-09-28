<?php

namespace App\Services;

use App\Models\CommuneReference;
use Illuminate\Support\Collection;

/** Local, indexed commune lookup used by public search only (never SEO pages). */
class CommuneDirectory
{
    public function __construct(
        private readonly AdministrativeGeography $administrative,
        private readonly CommuneTextNormalizer $normalizer,
    ) {}

    /** @return Collection<int, object> */
    public function suggest(string $term, int $limit = 12): Collection
    {
        $normalized = $this->normalizer->normalize($term);
        if ($normalized === '') {
            return collect();
        }

        $matches = CommuneReference::query()
            ->where('normalized_name', 'like', addcslashes($normalized, '%_\\').'%' )
            ->orderBy('normalized_name')
            ->orderBy('city_code')
            ->limit($limit * 3)
            ->get(['city_code', 'city_name', 'department_code', 'normalized_name']);
        $duplicates = $matches->countBy(fn (CommuneReference $commune): string => $commune->normalized_name);

        return $matches->take($limit)->map(function (CommuneReference $commune) use ($duplicates): object {
            $department = $this->administrative->department($commune->department_code);

            return (object) [
                'city_code' => $commune->city_code,
                'city_name' => $commune->city_name,
                'department_code' => $commune->department_code,
                'department_name' => $department['name'] ?? "Département {$commune->department_code}",
                'is_ambiguous' => ($duplicates[$commune->normalized_name] ?? 0) > 1,
            ];
        });
    }

    public function find(string $cityCode): ?object
    {
        $canonical = $this->administrative->canonicalCityCode($cityCode);
        if ($canonical === null) {
            return null;
        }

        $commune = CommuneReference::find($canonical);
        if ($commune === null) {
            return null;
        }
        $department = $this->administrative->department($commune->department_code);

        return (object) [
            'city_code' => $commune->city_code,
            'city_name' => $commune->city_name,
            'department_code' => $commune->department_code,
            'department_name' => $department['name'] ?? "Département {$commune->department_code}",
            'source_city_codes' => $this->administrative->sourceCityCodes($commune->city_code),
        ];
    }
}
