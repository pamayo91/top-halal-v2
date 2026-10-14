<?php

namespace Tests\Feature;

use App\Models\{CommuneReference, Restaurant};
use App\Services\CommuneTextNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BackfillQuickCityCodesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_dry_runs_then_updates_only_city_code_for_a_unique_normalized_local_match(): void
    {
        CommuneReference::create(['city_code' => '93031', 'city_name' => 'Épinay-sur-Seine', 'department_code' => '93', 'normalized_name' => app(CommuneTextNormalizer::class)->normalize('Épinay-sur-Seine')]);
        $restaurant = Restaurant::create(['name' => 'Quick Épinay', 'slug' => 'quick-epinay', 'status' => 'published', 'address_line1' => '1 Rue Test', 'postal_code' => '93800', 'city_name' => 'Epinay Sur Seine', 'city_code' => null, 'country_code' => 'FR', 'latitude' => 48.95, 'longitude' => 2.31]);
        $before = $restaurant->only(['city_name', 'postal_code', 'address_line1', 'country_code', 'latitude', 'longitude', 'slug', 'status']);
        Artisan::call('quick:backfill-city-codes', ['--json' => true]); $dry = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($dry['read_only']); $this->assertSame('unique_match', $dry['rows'][0]['status']); $this->assertNull($restaurant->fresh()->city_code);
        Artisan::call('quick:backfill-city-codes', ['--apply' => true, '--json' => true]); $apply = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(1, $apply['applied']); $this->assertSame('93031', $restaurant->fresh()->city_code); $this->assertSame($before, $restaurant->fresh()->only(array_keys($before)));
    }
}
