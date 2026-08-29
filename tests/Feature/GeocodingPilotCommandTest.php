<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Services\Geocoding\GeocodingService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class GeocodingPilotCommandTest extends TestCase
{
    use DatabaseMigrations;

    public function test_pilot_does_not_modify_restaurants(): void
    {
        $restaurant = Restaurant::create(['legacy_wp_id' => 101, 'name' => 'Test', 'slug' => 'test', 'status' => 'published', 'address' => '1 rue Test 75001 Paris', 'latitude' => '48.8500000', 'longitude' => '2.3500000']);
        $this->app->bind(GeocodingService::class, fn () => new class implements GeocodingService { public function search(string $q, int $l = 3): array { return ['ok'=>true,'query'=>$q,'features'=>[['postcode'=>'75001','city'=>'Paris','latitude'=>48.85,'longitude'=>2.35]],'error'=>null,'cached'=>false]; } public function reverse(float $a, float $b, int $l = 3): array { return ['ok'=>true,'query'=>'','features'=>[],'error'=>null,'cached'=>false]; } });
        $before = $restaurant->getAttributes(); $out = 'docs/generated/testing-geocoding-pilot.md';
        $this->artisan('data:geocoding-pilot', ['--out' => $out])->assertSuccessful();
        $this->assertSame($before, $restaurant->fresh()->getAttributes()); $this->assertTrue(File::exists(base_path($out))); File::delete(base_path($out));
    }
}
