<?php

namespace App\Services;

use Illuminate\Http\Request;

/**
 * The public directory has one location constraint: a city or a GPS point.
 * This value object normalises request input so changing another criterion
 * cannot accidentally turn a constrained listing into a global one.
 */
class RestaurantSearchState
{
    /** @return array{q:string,city_code:?string,ville:?string,categories:list<string>,features:list<string>,lat:?float,lng:?float,nearby:bool} */
    public function from(Request $request): array
    {
        $lat = $this->coordinate($request->input('lat'), -90, 90);
        $lng = $this->coordinate($request->input('lng'), -180, 180);
        $nearby = $lat !== null && $lng !== null;

        return [
            'q' => trim((string) $request->input('q')),
            // GPS deliberately wins: city and nearby are mutually exclusive.
            'city_code' => $nearby ? null : $this->string($request->input('city_code')),
            'ville' => $nearby ? null : $this->string($request->input('ville')),
            'categories' => $this->strings($request->input('categories', [])),
            'features' => $this->strings($request->input('features', [])),
            'lat' => $lat,
            'lng' => $lng,
            'nearby' => $nearby,
        ];
    }

    /** @param array{q:string,city_code:?string,ville:?string,categories:list<string>,features:list<string>,lat:?float,lng:?float,nearby:bool} $state */
    public function query(array $state): array
    {
        return array_filter([
            'q' => $state['q'] ?: null,
            'city_code' => $state['city_code'],
            'ville' => $state['ville'],
            'categories' => $state['categories'] ?: null,
            'features' => $state['features'] ?: null,
            'lat' => $state['nearby'] ? $state['lat'] : null,
            'lng' => $state['nearby'] ? $state['lng'] : null,
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private function coordinate(mixed $value, float $minimum, float $maximum): ?float
    {
        if (! is_numeric($value) || (float) $value < $minimum || (float) $value > $maximum) return null;

        return round((float) $value, 5);
    }

    private function string(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    /** @return list<string> */
    private function strings(mixed $values): array
    {
        return array_values(array_unique(array_filter((array) $values, fn (mixed $value): bool => is_string($value) && trim($value) !== '')));
    }
}
