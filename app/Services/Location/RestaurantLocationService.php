<?php

namespace App\Services\Location;

use App\Models\Restaurant;
use App\Services\AdminAudit;
use App\Services\Geocoding\GeocodingConfidence;

class RestaurantLocationService
{
    private const LOCATION_FIELDS = ['address_line1', 'address_line2', 'postal_code', 'city_name', 'city_code', 'country_code', 'latitude', 'longitude'];

    public function __construct(private GeocodingConfidence $confidence, private AdminAudit $audit) {}

    public function update(Restaurant $restaurant, array $data): Restaurant
    {
        $source = $data['location_update_source'] ?? 'form';
        unset($data['address_suggestion'], $data['location_update_source']);
        $before = collect($restaurant->only(array_merge(self::LOCATION_FIELDS, ['geocoding_provider', 'geocoding_source_id', 'geocoding_precision', 'geocoding_status', 'geocoding_score'])));
        $locationChanged = $before->only(self::LOCATION_FIELDS)->some(fn ($value, $field) => (string) $value !== (string) ($data[$field] ?? $value));
        $coordinatesChanged = (string) $restaurant->latitude !== (string) ($data['latitude'] ?? $restaurant->latitude) || (string) $restaurant->longitude !== (string) ($data['longitude'] ?? $restaurant->longitude);

        if ($locationChanged && $source === 'map') {
            $data += ['geocoding_status' => 'MANUAL', 'geocoding_precision' => 'MANUAL', 'geocoding_review_reason' => 'manual_marker_correction', 'manually_verified_at' => now()];
        } elseif ($locationChanged && $source === 'manual') {
            $data += ['geocoding_status' => 'REVIEW_REQUIRED', 'geocoding_review_reason' => 'manual_address_entry'];
        }

        $restaurant->fill($data);
        if ($locationChanged) {
            $this->refreshQualification($restaurant, $source);
            if ((string) $restaurant->getOriginal('city_code') !== (string) $restaurant->city_code && $restaurant->locations()->exists()) $restaurant->location_review_reason = 'geography_associations_require_review';
        }
        $restaurant->save();

        if ($locationChanged) {
            $after = $restaurant->only(array_merge(self::LOCATION_FIELDS, ['geocoding_provider', 'geocoding_source_id', 'geocoding_precision', 'geocoding_status', 'geocoding_score']));
            $this->audit->record('restaurant.location_updated', $restaurant, ['source' => $source, 'before' => $before->all(), 'after' => $after, 'coordinates_changed' => $coordinatesChanged]);
        }
        return $restaurant;
    }

    private function refreshQualification(Restaurant $restaurant, string $source): void
    {
        if ($source === 'map' || $source === 'manual') { $restaurant->proximity_status = $restaurant->latitude === null || $restaurant->longitude === null ? 'EXCLUDED' : 'REVIEW_REQUIRED'; return; }
        $feature = ['postcode'=>$restaurant->postal_code, 'city'=>$restaurant->city_name, 'type'=>$restaurant->geocoding_precision, 'score'=>$restaurant->geocoding_score];
        $decision = $this->confidence->decide($feature, $source === 'autocomplete' ? 0.0 : null, $restaurant->postal_code, $restaurant->city_name, $restaurant->latitude !== null && $restaurant->longitude !== null, false, false);
        $restaurant->geocoding_status = $decision['status']; $restaurant->geocoding_review_reason = $decision['reason'];
        $restaurant->address_confidence = $decision['status'] === 'VERIFIED' ? 'VERIFIED' : ($decision['status'] === 'HIGH_CONFIDENCE' ? 'HIGH_CONFIDENCE' : ($decision['status'] === 'APPROXIMATE' ? 'APPROXIMATE' : 'MISSING'));
        $restaurant->location_precision = strtoupper((string) ($restaurant->geocoding_precision ?: 'UNKNOWN'));
        $restaurant->proximity_status = in_array($decision['status'], ['VERIFIED', 'HIGH_CONFIDENCE'], true) ? 'ELIGIBLE' : 'REVIEW_REQUIRED';
    }
}
