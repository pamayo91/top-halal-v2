<?php

namespace App\Services\Location;

use App\Models\Restaurant;
use App\Services\AdminAudit;

class RestaurantLocationService
{
    private const LOCATION_FIELDS = ['address_line1', 'address_line2', 'postal_code', 'city_name', 'city_code', 'country_code', 'latitude', 'longitude'];

    public function __construct(private AdminAudit $audit) {}

    public function update(Restaurant $restaurant, array $data): Restaurant
    {
        $source = $data['location_update_source'] ?? 'form';
        unset($data['address_suggestion'], $data['location_update_source']);
        $before = collect($restaurant->only(self::LOCATION_FIELDS));
        $locationChanged = $before->only(self::LOCATION_FIELDS)->some(fn ($value, $field) => (string) $value !== (string) ($data[$field] ?? $value));
        $coordinatesChanged = (string) $restaurant->latitude !== (string) ($data['latitude'] ?? $restaurant->latitude) || (string) $restaurant->longitude !== (string) ($data['longitude'] ?? $restaurant->longitude);

        // A contributor can refine a marker after choosing an authoritative
        // suggestion. That gesture must never rewrite the selected address or
        // trigger reverse geocoding/qualification changes.
        if (in_array($source, ['public_map', 'owner_map'], true)
            && $coordinatesChanged
            && collect(array_keys($data))->every(fn (string $field) => in_array($field, ['latitude', 'longitude'], true))) {
            $restaurant->fill($data);
            $restaurant->save();
            $this->audit->record('restaurant.location_updated', $restaurant, [
                'source' => $source,
                'before' => $before->all(),
                'after' => $restaurant->only(self::LOCATION_FIELDS),
                'coordinates_changed' => true,
            ]);

            return $restaurant;
        }

        $restaurant->fill($data);
        $restaurant->save();

        if ($locationChanged) {
            $after = $restaurant->only(self::LOCATION_FIELDS);
            $this->audit->record('restaurant.location_updated', $restaurant, ['source' => $source, 'before' => $before->all(), 'after' => $after, 'coordinates_changed' => $coordinatesChanged]);
        }
        return $restaurant;
    }

    /** Apply the provider selection first, then optionally preserve a manual marker refinement. */
    public function applySelectedSuggestion(Restaurant $restaurant, array $selection, ?float $latitude = null, ?float $longitude = null, string $markerSource = 'public_map'): Restaurant
    {
        $this->update($restaurant, [...$selection, 'location_update_source' => 'autocomplete']);

        if ($latitude !== null && $longitude !== null
            && ((string) $restaurant->latitude !== (string) $latitude || (string) $restaurant->longitude !== (string) $longitude)) {
            $this->update($restaurant, [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'location_update_source' => $markerSource,
            ]);
        }

        return $restaurant;
    }
}
