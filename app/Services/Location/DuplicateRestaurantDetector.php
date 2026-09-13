<?php

namespace App\Services\Location;

use App\Models\Restaurant;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DuplicateRestaurantDetector
{
    /**
     * The single duplicate engine used by the public preview, the final public
     * submission and the back office. Exact address + identity is required to
     * block: nearby restaurants and food-court co-location remain review work.
     */
    public function assess(array $values, ?int $excludeId = null): DuplicateRestaurantAssessment
    {
        $values = $this->normalisedValues($values);
        $certain = collect();
        $potential = collect();

        foreach ($this->candidatePool($values, $excludeId) as $candidate) {
            $candidateValues = $this->normalisedValues($candidate->only([
                'name', 'address_line1', 'postal_code', 'city_name', 'city_code', 'latitude', 'longitude', 'phone',
            ]));
            $sameAddress = $this->sameStructuredAddress($values, $candidateValues);
            $sameName = $this->sameName($values['name'], $candidateValues['name']);
            $samePhone = $values['phone'] !== '' && $values['phone'] === $candidateValues['phone'];
            $nearby = $this->distanceKm($values, $candidateValues) !== null && $this->distanceKm($values, $candidateValues) <= 0.25;
            $historical = $candidate->trashed() || $candidate->status === 'archived';
            $active = in_array($candidate->status, ['published', 'pending'], true) && ! $candidate->trashed();

            if ($sameAddress && ($sameName || $samePhone)) {
                $match = ['restaurant' => $candidate, 'reason' => $samePhone && ! $sameName ? 'same_address_and_phone' : 'same_structured_address_and_name'];
                if (! $active) $potential->push([...$match, 'reason' => $historical ? 'archived_exact_match' : 'inactive_exact_match']);
                else $certain->push($match);
                continue;
            }

            if ($sameAddress) {
                $potential->push(['restaurant' => $candidate, 'reason' => $historical ? 'archived_same_address' : 'same_address_different_name']);
                continue;
            }

            if (($sameName && $nearby) || ($samePhone && $this->sameLocality($values, $candidateValues))) {
                $potential->push(['restaurant' => $candidate, 'reason' => $historical ? 'archived_nearby_match' : ($samePhone ? 'same_phone_same_locality' : 'nearby_similar_name')]);
            }
        }

        return new DuplicateRestaurantAssessment($certain->unique(fn (array $match) => $match['restaurant']->id)->values(), $potential->reject(fn (array $match) => $certain->contains(fn (array $certainMatch) => $certainMatch['restaurant']->id === $match['restaurant']->id))->unique(fn (array $match) => $match['restaurant']->id)->values());
    }

    /** Informative candidates for existing records; never changes any record. */
    public function candidates(Restaurant $restaurant, array $values = []): array
    {
        return $this->assess(array_replace($restaurant->only(['name', 'address_line1', 'postal_code', 'city_name', 'city_code', 'latitude', 'longitude', 'phone']), $values), $restaurant->getKey())
            ->candidates()->pluck('restaurant')->all();
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
        return $this->assess($values)->candidates()->pluck('restaurant')
            ->filter(fn (Restaurant $candidate) => ! $candidate->trashed() && $candidate->status === 'published')
            ->merge($this->similarNames((string) ($values['name'] ?? ''), 8))
            ->unique('id')
            ->take(5)->values();
    }

    /** @return Collection<int, Restaurant> */
    private function candidatePool(array $values, ?int $excludeId): Collection
    {
        $query = Restaurant::withTrashed()->when($excludeId, fn ($query) => $query->whereKeyNot($excludeId));
        $query->where(function ($query) use ($values): void {
            $added = false;
            if ($values['city_code'] !== '') { $query->where('city_code', $values['city_code']); $added = true; }
            if ($values['postal_code'] !== '' && $values['city_name'] !== '') {
                $method = $added ? 'orWhere' : 'where';
                $query->{$method}(fn ($locality) => $locality->where('postal_code', $values['postal_code'])->whereRaw('LOWER(city_name) = ?', [Str::lower($values['city_name'])]));
                $added = true;
            }
            if ($values['name'] !== '') {
                $method = $added ? 'orWhereRaw' : 'whereRaw';
                $query->{$method}('LOWER(name) LIKE ?', ['%'.str_replace(['%', '_'], ['\\%', '\\_'], Str::lower($values['name'])).'%']);
                $added = true;
            }
            if (is_numeric($values['latitude']) && is_numeric($values['longitude'])) {
                $delta = 0.004; // candidate prefilter; the exact 250m decision stays in PHP below.
                $method = $added ? 'orWhere' : 'where';
                $query->{$method}(fn ($coordinates) => $coordinates->whereBetween('latitude', [(float) $values['latitude'] - $delta, (float) $values['latitude'] + $delta])->whereBetween('longitude', [(float) $values['longitude'] - $delta, (float) $values['longitude'] + $delta]));
            }
        });

        // Do not cap this safety check: a large commune must not make an exact
        // same-address comparison silently disappear from the final POST.
        return $query->get();
    }

    private function sameStructuredAddress(array $left, array $right): bool
    {
        return $left['address_line1'] !== '' && $left['address_line1'] === $right['address_line1'] && $this->sameLocality($left, $right);
    }

    private function sameLocality(array $left, array $right): bool
    {
        if ($left['city_code'] !== '' && $right['city_code'] !== '') return $left['city_code'] === $right['city_code'];
        return $left['postal_code'] !== '' && $left['postal_code'] === $right['postal_code'] && $left['city_name'] !== '' && $left['city_name'] === $right['city_name'];
    }

    private function sameName(string $left, string $right): bool
    {
        if ($left === '' || $right === '') return false;
        if ($left === $right || $this->comparableName($left) === $this->comparableName($right)) return true;
        $longest = max(mb_strlen($left), mb_strlen($right));
        return $longest >= 5 && levenshtein($left, $right) / $longest <= 0.12;
    }

    private function comparableName(string $name): string
    {
        return collect(explode(' ', $name))->reject(fn (string $part) => in_array($part, ['restaurant', 'resto', 'halal', 'le', 'la', 'les', 'de', 'du', 'des'], true))->implode(' ');
    }

    private function distanceKm(array $left, array $right): ?float
    {
        if (! is_numeric($left['latitude']) || ! is_numeric($left['longitude']) || ! is_numeric($right['latitude']) || ! is_numeric($right['longitude'])) return null;
        $latDelta = deg2rad((float) $right['latitude'] - (float) $left['latitude']);
        $lngDelta = deg2rad((float) $right['longitude'] - (float) $left['longitude']);
        $a = sin($latDelta / 2) ** 2 + cos(deg2rad((float) $left['latitude'])) * cos(deg2rad((float) $right['latitude'])) * sin($lngDelta / 2) ** 2;
        return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function normalisedValues(array $values): array
    {
        return [
            'name' => $this->normalise($values['name'] ?? null), 'address_line1' => $this->normalise($values['address_line1'] ?? null),
            'postal_code' => preg_replace('/\\s+/', '', (string) ($values['postal_code'] ?? '')) ?: '', 'city_name' => $this->normalise($values['city_name'] ?? null),
            'city_code' => strtoupper(trim((string) ($values['city_code'] ?? ''))), 'latitude' => $values['latitude'] ?? null, 'longitude' => $values['longitude'] ?? null,
            'phone' => $this->normalisePhone($values['phone'] ?? null),
        ];
    }

    private function normalisePhone(?string $value): string
    {
        $phone = preg_replace('/\\D+/', '', (string) $value) ?: '';
        if (str_starts_with($phone, '0033')) return '0'.substr($phone, 4);
        if (str_starts_with($phone, '33') && strlen($phone) === 11) return '0'.substr($phone, 2);
        return $phone;
    }

    private function normalise(?string $value): string { return Str::of((string) $value)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->value(); }
}
