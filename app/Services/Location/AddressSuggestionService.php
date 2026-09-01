<?php

namespace App\Services\Location;

use App\Services\Geocoding\GeocodingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/** Provider-neutral address suggestions. Tokens deliberately keep provider payload out of the browser. */
class AddressSuggestionService
{
    public function __construct(private GeocodingService $geocoder, private AddressLineParser $lines) {}

    /** @return array<int, array{token:string,label:string,feature:array}> */
    public function suggest(string $query, int $limit = 6): array
    {
        $query = trim(preg_replace('/\s+/', ' ', $query));
        if (mb_strlen($query) < 3) return [];

        $result = $this->geocoder->search($query, min(max($limit, 1), 8));
        if (!$result['ok']) return [];

        return collect($result['features'])->filter(fn (array $feature) => filled($feature['label'] ?? null))->map(function (array $feature) use ($query): array {
            $token = (string) Str::uuid();
            Cache::put($this->key($token), $feature, now()->addMinutes(15));
            return ['token' => $token, 'label' => $this->label($feature), 'feature' => $feature];
        })->values()->all();
    }

    public function resolve(string $token): ?array
    {
        return Str::isUuid($token) ? Cache::get($this->key($token)) : null;
    }

    public function label(array $feature): string
    {
        $line = trim((string) ($feature['label'] ?? ''));
        $city = trim(implode(' ', array_filter([(string) ($feature['postcode'] ?? ''), (string) ($feature['city'] ?? '')])));
        return $city && !str_contains(mb_strtolower($line), mb_strtolower($city)) ? $line.' — '.$city : $line;
    }

    /** Maps the narrow provider contract to the application address contract. */
    public function structured(array $feature): array
    {
        $label = trim((string) ($feature['label'] ?? ''));
        $postcode = (string) ($feature['postcode'] ?? '');
        $city = (string) ($feature['city'] ?? '');
        $line = $this->lines->fromProviderLabel($label, $postcode, $city);
        return [
            'address_line1' => $line ?: null, 'postal_code' => $postcode ?: null, 'city_name' => $city ?: null,
            'city_code' => $feature['citycode'] ?? null, 'country_code' => ($feature['citycode'] ?? null) ? 'FR' : null,
            'latitude' => $feature['latitude'] ?? null, 'longitude' => $feature['longitude'] ?? null,
            'geocoding_provider' => 'geoplateforme', 'geocoding_source_id' => $feature['id'] ?? null,
            'geocoding_precision' => $feature['type'] ?? null, 'geocoding_score' => $feature['score'] ?? null,
            'geocoded_at' => now(),
        ];
    }

    private function key(string $token): string { return 'address-suggestion:'.$token; }
}
