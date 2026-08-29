<?php

namespace Tests\Feature;

use App\Services\Geocoding\GeoPlateformeProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeoPlateformeProviderTest extends TestCase
{
    public function test_it_parses_and_caches_a_geoplateforme_response(): void
    {
        Cache::flush();
        $payload = ['features' => [[
            'properties' => ['label' => '1 rue Test 75001 Paris', 'score' => 0.99, 'type' => 'housenumber', 'id' => '75101_0001_00001', 'postcode' => '75001', 'city' => 'Paris', 'citycode' => '75101'],
            'geometry' => ['coordinates' => [2.35, 48.85]],
        ]]];
        Http::fake(['https://data.geopf.fr/geocodage/search*' => Http::response($payload)]);
        $provider = app(GeoPlateformeProvider::class); $first = $provider->search('1 rue Test 75001 Paris'); $second = $provider->search('1 rue Test 75001 Paris');
        $this->assertTrue($first['ok']); $this->assertFalse($first['cached']); $this->assertSame('75001', $first['features'][0]['postcode']); $this->assertSame(48.85, $first['features'][0]['latitude']); $this->assertTrue($second['cached']); Http::assertSentCount(1);
    }

    public function test_it_handles_unavailable_provider_without_throwing(): void
    {
        Cache::flush(); Http::fake(['*' => Http::response([], 503)]);
        $result = app(GeoPlateformeProvider::class)->reverse(48.85, 2.35);
        $this->assertFalse($result['ok']); $this->assertSame('HTTP 503', $result['error']);
    }
}
