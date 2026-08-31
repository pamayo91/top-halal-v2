<?php

namespace App\Services\Location;

use App\Models\Restaurant;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DuplicateRestaurantDetector
{
    /** Informative candidates only: co-located restaurants are never automatically duplicates. */
    public function candidates(Restaurant $restaurant, array $values = []): array
    {
        $lat = $values['latitude'] ?? $restaurant->latitude;
        $lng = $values['longitude'] ?? $restaurant->longitude;
        if (!is_numeric($lat) || !is_numeric($lng)) return $this->similarNames($values['name'] ?? $restaurant->name)->all();
        $cap = Restaurant::query()->getConnection()->getDriverName() === 'sqlite' ? 'min(1.0, %s)' : 'least(1, %s)';
        $formula = 'cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))';
        $distance = '(6371 * acos('.sprintf($cap, $formula).'))';
        $name = $this->normalise($values['name'] ?? $restaurant->name);
        $address = $this->normalise($values['address_line1'] ?? $restaurant->address_line1);
        return Restaurant::query()->when($restaurant->getKey(), fn ($query, $id) => $query->whereKeyNot($id))->whereNotNull('latitude')->whereNotNull('longitude')
            ->select('restaurants.*')->selectRaw("{$distance} as distance_km", [(float) $lat, (float) $lng, (float) $lat])
            ->orderBy('distance_km')->limit(30)->get()->filter(fn (Restaurant $candidate) => (float) $candidate->distance_km <= 0.25)
            ->filter(fn (Restaurant $candidate) => $this->normalise($candidate->name) === $name || ($address !== '' && $this->normalise($candidate->address_line1) === $address) || (filled($restaurant->phone) && $candidate->phone === $restaurant->phone))
            ->values()->all();
    }

    /** @return Collection<int, Restaurant> */
    public function similarNames(string $name, int $limit = 5): Collection
    {
        $normalised = $this->normalise($name);
        if (mb_strlen($normalised) < 2) return collect();

        return Restaurant::query()->where('status', 'published')
            ->whereRaw('LOWER(name) LIKE ?', ['%'.str_replace(['%', '_'], ['\\%', '\\_'], Str::lower(trim($name))).'%'])
            ->orderBy('name')->limit($limit)->get();
    }

    /** @return Collection<int, Restaurant> */
    public function publicCandidates(array $values): Collection
    {
        $nameMatches = $this->similarNames((string) ($values['name'] ?? ''), 8);
        $lat = $values['latitude'] ?? null;
        $lng = $values['longitude'] ?? null;
        if (!is_numeric($lat) || !is_numeric($lng)) return $nameMatches->take(5)->values();

        $temporary = new Restaurant([
            'name' => (string) ($values['name'] ?? ''),
            'address_line1' => (string) ($values['address_line1'] ?? ''),
            'phone' => (string) ($values['phone'] ?? ''),
            'latitude' => $lat,
            'longitude' => $lng,
        ]);

        return collect($this->candidates($temporary, $values))
            ->filter(fn (Restaurant $candidate) => $candidate->status === 'published')
            ->merge($nameMatches)->unique('id')->take(5)->values();
    }

    private function normalise(?string $value): string { return Str::of((string) $value)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->value(); }
}
