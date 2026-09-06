<?php

namespace App\Services;

use Illuminate\Support\Str;

/** Lightweight, versioned French administrative reference; no public request uses a remote API. */
class AdministrativeGeography
{
    /** @return array{code:string,name:string,slug:string}|null */
    public function department(string $code): ?array
    {
        $code = strtoupper(trim($code));
        $department = config("administrative-geography.departments.{$code}");

        return is_array($department)
            ? ['code' => $code, 'name' => $department['name'], 'slug' => Str::slug($department['name'])]
            : null;
    }

    /** @return array{code:string,name:string,slug:string}|null */
    public function region(string $code): ?array
    {
        $code = trim($code);
        $name = config("administrative-geography.regions.{$code}");

        return is_string($name)
            ? ['code' => $code, 'name' => $name, 'slug' => Str::slug($name)]
            : null;
    }

    /** @return array{city_code:string,department:array{code:string,name:string,slug:string},region:array{code:string,name:string,slug:string}}|null */
    public function resolve(string $cityCode): ?array
    {
        $cityCode = $this->canonicalCityCode($cityCode);
        if ($cityCode === null) {
            return null;
        }

        $departmentCode = $this->departmentCodeForCityCode($cityCode);
        $department = $departmentCode === null ? null : $this->department($departmentCode);
        $region = $department === null ? null : $this->region((string) config("administrative-geography.departments.{$department['code']}.region"));

        return $department !== null && $region !== null
            ? compact('cityCode', 'department', 'region') + ['city_code' => $cityCode]
            : null;
    }

    public function canonicalCityCode(?string $cityCode): ?string
    {
        $cityCode = strtoupper(trim((string) $cityCode));
        if (! preg_match('/^(?:\d{5}|2[AB]\d{3})$/', $cityCode)) {
            return null;
        }

        if ($cityCode === '75056' || preg_match('/^751(?:0[1-9]|1\d|20)$/', $cityCode)) {
            return '75056';
        }

        if ($cityCode === '69123' || preg_match('/^6938[1-9]$/', $cityCode)) {
            return '69123';
        }

        if ($cityCode === '13055' || preg_match('/^132(?:0[1-9]|1[0-6])$/', $cityCode)) {
            return '13055';
        }

        return $cityCode;
    }

    /** @return list<string> */
    public function sourceCityCodes(string $canonicalCityCode): array
    {
        return match ($canonicalCityCode) {
            '75056' => array_merge(['75056'], array_map(fn (int $number): string => '751'.str_pad((string) $number, 2, '0', STR_PAD_LEFT), range(1, 20))),
            '69123' => array_merge(['69123'], array_map(fn (int $number): string => '6938'.$number, range(1, 9))),
            '13055' => array_merge(['13055'], array_map(fn (int $number): string => '132'.str_pad((string) $number, 2, '0', STR_PAD_LEFT), range(1, 16))),
            default => [$canonicalCityCode],
        };
    }

    /** @return list<array{code:string,name:string,slug:string}> */
    public function departmentsForRegion(string $regionCode): array
    {
        return collect(config('administrative-geography.departments'))
            ->filter(fn (array $department): bool => $department['region'] === $regionCode)
            ->map(fn (array $department, string $code): array => ['code' => $code, 'name' => $department['name'], 'slug' => Str::slug($department['name'])])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    private function departmentCodeForCityCode(string $cityCode): ?string
    {
        if (preg_match('/^(2[AB])\d{3}$/', $cityCode, $matches)) {
            return $matches[1];
        }

        $overseasCode = substr($cityCode, 0, 3);
        if (in_array($overseasCode, ['971', '972', '973', '974', '975', '976', '977', '978'], true)) {
            return $overseasCode;
        }

        return substr($cityCode, 0, 2);
    }
}
