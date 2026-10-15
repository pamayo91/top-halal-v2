<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UnresolvedRestaurantCitiesAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_unresolved_city_audit_is_read_only_and_classifies_missing_and_parasite_city_values(): void
    {
        $ordinary = Restaurant::create(['name' => 'Quick Example', 'slug' => 'quick-example', 'status' => 'published', 'address_line1' => '1 Rue Exemple', 'postal_code' => '75001', 'city_name' => 'Paris', 'city_code' => null, 'country_code' => 'FR', 'latitude' => 48.8600, 'longitude' => 2.3400]);
        $parasite = Restaurant::create(['name' => 'Quick Lab 997', 'slug' => 'quick-lab-997', 'status' => 'published', 'address_line1' => '1 Rue Exemple', 'postal_code' => '93300', 'city_name' => 'Auber Lab', 'city_code' => null, 'country_code' => 'FR', 'latitude' => 48.9000, 'longitude' => 2.3600]);
        $before = $ordinary->only(['city_name', 'city_code', 'postal_code', 'address_line1', 'country_code', 'latitude', 'longitude']);
        $writes = [];
        DB::listen(function ($query) use (&$writes): void {
            if (preg_match('/^\\s*(insert|update|delete|replace)\\b/i', $query->sql)) $writes[] = $query->sql;
        });

        $this->assertSame(0, Artisan::call('geography:audit-unresolved-restaurant-cities', ['--json' => true]));
        $report = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertTrue($report['read_only']);
        $this->assertSame(2, $report['summary']['total_analyzed']);
        $this->assertSame(1, $report['summary']['by_category']['A — city_code absent']);
        $this->assertSame(1, $report['summary']['by_category']['G — Valeur manifestement anormale ou donnée parasite']);
        $this->assertSame([], $writes);
        $this->assertSame($before, $ordinary->fresh()->only(array_keys($before)));
        $this->assertSame($parasite->id, $report['summary']['ambiguous_restaurant_ids'][0]);
    }
}
