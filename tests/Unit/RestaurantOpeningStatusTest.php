<?php

namespace Tests\Unit;

use App\Models\RestaurantOpeningHour;
use App\Services\RestaurantOpeningStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Tests\TestCase;

class RestaurantOpeningStatusTest extends TestCase
{
    public function test_it_marks_a_restaurant_open_during_a_service(): void
    {
        $hours = $this->hours([['day' => 'monday', 'opens_at' => '10:00', 'closes_at' => '23:00']]);

        $this->assertSame('Ouvert actuellement · Ferme à 23:00', (new RestaurantOpeningStatus)->for($hours, CarbonImmutable::parse('2026-08-31 18:30', 'Europe/Paris'))['message']);
    }

    public function test_it_reports_the_gap_between_two_services(): void
    {
        $hours = $this->hours([
            ['day' => 'monday', 'opens_at' => '10:00', 'closes_at' => '12:00', 'slot' => 1],
            ['day' => 'monday', 'opens_at' => '18:00', 'closes_at' => '23:00', 'slot' => 2],
        ]);

        $this->assertSame('Fermé actuellement · Ouvre à 18:00', (new RestaurantOpeningStatus)->for($hours, CarbonImmutable::parse('2026-08-31 14:00', 'Europe/Paris'))['message']);
    }

    public function test_it_reports_a_closed_day(): void
    {
        $hours = $this->hours([['day' => 'sunday', 'is_closed' => true]]);

        $this->assertSame('Fermé aujourd’hui', (new RestaurantOpeningStatus)->for($hours, CarbonImmutable::parse('2026-09-06 12:00', 'Europe/Paris'))['message']);
    }

    public function test_it_finds_tomorrows_single_service_after_today_has_closed(): void
    {
        $hours = $this->hours([
            ['day' => 'monday', 'opens_at' => '10:00', 'closes_at' => '14:00'],
            ['day' => 'tuesday', 'opens_at' => '11:00', 'closes_at' => '22:00'],
        ]);

        $this->assertSame('Fermé actuellement · Ouvre demain à 11:00', (new RestaurantOpeningStatus)->for($hours, CarbonImmutable::parse('2026-08-31 18:00', 'Europe/Paris'))['message']);
    }

    public function test_it_handles_an_overnight_service_from_the_previous_day(): void
    {
        $hours = $this->hours([
            ['day' => 'monday', 'opens_at' => '18:00', 'closes_at' => '02:00'],
            ['day' => 'tuesday', 'is_closed' => true],
        ]);

        $this->assertSame('Ouvert actuellement · Ferme à 02:00', (new RestaurantOpeningStatus)->for($hours, CarbonImmutable::parse('2026-09-01 01:00', 'Europe/Paris'))['message']);
    }

    public function test_it_omits_the_dynamic_status_when_todays_data_is_incomplete(): void
    {
        $hours = $this->hours([['day' => 'monday', 'opens_at' => '10:00']]);

        $this->assertNull((new RestaurantOpeningStatus)->for($hours, CarbonImmutable::parse('2026-08-31 12:00', 'Europe/Paris'))['message']);
    }

    private function hours(array $attributes): Collection
    {
        return collect($attributes)->map(fn (array $attributes) => new RestaurantOpeningHour($attributes));
    }
}
