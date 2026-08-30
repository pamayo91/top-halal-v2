<?php

namespace App\Services\Location;

use App\Models\Restaurant;
use Illuminate\Support\Str;

class DuplicateRestaurantDetector
{
    /** Informative candidates only: co-located restaurants are never automatically duplicates. */
    public function candidates(Restaurant $restaurant, array $values = []): array
    {
        $lat = $values['latitude'] ?? $restaurant->latitude;
        $lng = $values['longitude'] ?? $restaurant->longitude;
        if (!is_numeric($lat) || !is_numeric($lng)) return [];
        $distance = '(6371 * acos(least(1, cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))))';
        $name = $this->normalise($values['name'] ?? $restaurant->name);
        $address = $this->normalise($values['address_line1'] ?? $restaurant->address_line1);
        return Restaurant::query()->whereKeyNot($restaurant->getKey())->whereNotNull('latitude')->whereNotNull('longitude')
            ->select('restaurants.*')->selectRaw("{$distance} as distance_km", [(float) $lat, (float) $lng, (float) $lat])
            ->having('distance_km', '<=', 0.25)->orderBy('distance_km')->limit(10)->get()
            ->filter(fn (Restaurant $candidate) => $this->normalise($candidate->name) === $name || ($address !== '' && $this->normalise($candidate->address_line1) === $address) || (filled($restaurant->phone) && $candidate->phone === $restaurant->phone))
            ->values()->all();
    }

    private function normalise(?string $value): string { return Str::of((string) $value)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->value(); }
}
