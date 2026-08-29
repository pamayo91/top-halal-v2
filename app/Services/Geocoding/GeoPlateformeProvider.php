<?php

namespace App\Services\Geocoding;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GeoPlateformeProvider implements GeocodingService
{
    public function search(string $query, int $limit = 3): array
    {
        return $this->request('search', ['q' => trim($query), 'limit' => $limit]);
    }

    public function reverse(float $latitude, float $longitude, int $limit = 3): array
    {
        return $this->request('reverse', ['lat' => $latitude, 'lon' => $longitude, 'limit' => $limit]);
    }

    private function request(string $path, array $params): array
    {
        $key = 'geocoding-pilot:'.hash('sha256', $path.'|'.http_build_query($params));
        if (Cache::has($key)) return [...Cache::get($key), 'cached' => true];
        try {
            $response = Http::acceptJson()->withUserAgent(config('services.geoplateforme.user_agent'))
                ->timeout((int) config('services.geoplateforme.timeout', 10))->retry(2, 500)
                ->get(rtrim(config('services.geoplateforme.base_url'), '/').'/'.$path, $params);
            $data = $response->json();
            $result = $response->successful() && is_array($data)
                ? ['ok' => true, 'query' => http_build_query($params), 'features' => $this->features($data['features'] ?? []), 'error' => null, 'cached' => false]
                : ['ok' => false, 'query' => http_build_query($params), 'features' => [], 'error' => 'HTTP '.$response->status(), 'cached' => false];
        } catch (\Throwable $e) { $result = ['ok' => false, 'query' => http_build_query($params), 'features' => [], 'error' => class_basename($e), 'cached' => false]; }
        Cache::put($key, $result, now()->addDays(30));
        usleep(250000); // Respectful pilot rate: maximum four uncached calls per second.
        return $result;
    }

    private function features(array $features): array
    {
        return collect($features)->map(function ($feature): array {
            $p = $feature['properties'] ?? []; $c = $feature['geometry']['coordinates'] ?? [];
            return ['label' => $p['label'] ?? null, 'score' => isset($p['score']) ? (float) $p['score'] : null, 'type' => $p['type'] ?? null, 'id' => $p['id'] ?? null, 'postcode' => $p['postcode'] ?? null, 'city' => $p['city'] ?? null, 'citycode' => $p['citycode'] ?? null, 'latitude' => isset($c[1]) ? (float) $c[1] : null, 'longitude' => isset($c[0]) ? (float) $c[0] : null];
        })->values()->all();
    }
}
