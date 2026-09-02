<?php

namespace App\Services\Location;

use App\Models\Restaurant;
use App\Services\Geocoding\GeocodingService;
use App\Services\Geocoding\GeocodingConfidence;
use Illuminate\Support\Str;

class MissingGpsAutoGeocoder
{
    public function __construct(private GeocodingService $geocoder, private GeocodingConfidence $confidence) {}

    /** @return array{outcome:string,feature:?array,fields:array} */
    public function locate(Restaurant $restaurant): array
    {
        if (!$this->hasUsableAddress($restaurant)) return ['outcome'=>'unusable_address', 'feature'=>null, 'fields'=>[]];
        if (filled($restaurant->country_code) && strtoupper((string) $restaurant->country_code) !== 'FR') return ['outcome'=>'unexpected_country', 'feature'=>null, 'fields'=>[]];

        $response = $this->geocoder->search($this->query($restaurant), 6);
        if (!$response['ok']) return ['outcome'=>'provider_error', 'feature'=>null, 'fields'=>[]];
        if (!$response['features']) return ['outcome'=>'no_result', 'feature'=>null, 'fields'=>[]];

        foreach ($response['features'] as $feature) {
            if ($this->acceptable($restaurant, $feature)) return ['outcome'=>(string) $feature['type'], 'feature'=>$feature, 'fields'=>$this->fields($restaurant, $feature)];
        }
        return ['outcome'=>'contradiction', 'feature'=>$response['features'][0], 'fields'=>[]];
    }

    private function hasUsableAddress(Restaurant $r): bool { return filled($r->address_line1) && (filled($r->postal_code) || filled($r->city_name) || filled($r->city_code)); }
    private function query(Restaurant $r): string { return implode(', ', array_filter([$r->address_line1, $r->address_line2, $r->postal_code, $r->city_name, $r->country_code])); }

    private function acceptable(Restaurant $r, array $f): bool
    {
        if (!in_array($f['type'] ?? null, ['housenumber', 'street'], true) || !is_numeric($f['latitude'] ?? null) || !is_numeric($f['longitude'] ?? null)) return false;
        $postalMatch = filled($r->postal_code) && (string) $r->postal_code === (string) ($f['postcode'] ?? '');
        $cityCodeMatch = filled($r->city_code) && (string) $r->city_code === (string) ($f['citycode'] ?? '');
        $cityMatch = filled($r->city_name) && filled($f['city'] ?? null) && $this->confidence->sameCity((string) $r->city_name, (string) $f['city']);
        $postalConflict = filled($r->postal_code) && filled($f['postcode'] ?? null) && !$postalMatch;
        $cityCodeConflict = filled($r->city_code) && filled($f['citycode'] ?? null) && !$cityCodeMatch;
        $cityConflict = filled($r->city_name) && filled($f['city'] ?? null) && !$cityMatch;
        if ($postalConflict || $cityCodeConflict || $cityConflict) return false;
        return ($f['type'] === 'housenumber' && ($postalMatch || $cityMatch || $cityCodeMatch)) || ($f['type'] === 'street' && ($postalMatch || $cityCodeMatch));
    }

    private function fields(Restaurant $r, array $f): array
    {
        $fields = [];
        // Never replace a legacy or previously verified coordinate; complete only the missing half if needed.
        if ($r->latitude === null) $fields['latitude'] = (float) $f['latitude'];
        if ($r->longitude === null) $fields['longitude'] = (float) $f['longitude'];
        return $fields;
    }
}
