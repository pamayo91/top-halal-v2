<?php

namespace Tests\Unit;

use App\Services\Quick\QuickRestaurantSource;
use Tests\TestCase;

class QuickRestaurantSourceTest extends TestCase
{
    public function test_it_parses_official_halal_flags_without_inventing_a_certifier(): void
    {
        $record=(new QuickRestaurantSource)->normalise(['attributes'=>['name'=>'Test','slug'=>'test','address1'=>'1 RUE TEST','postalCode'=>'75001','city'=>'PARIS','halal'=>'Oui','certifHalal'=>'Oui','drive'=>'Oui','diningMonday'=>'11:00 - 22:00']]);
        $this->assertSame('halal_confirmed',$record['quick_halal_status']);
        $this->assertNull($record['quick_halal_certifier']);
        $this->assertStringContainsString('certifHalal=Oui',$record['quick_halal_evidence']);
        $this->assertSame(['monday'=>'11:00 - 22:00'],$record['quick_opening_hours']);
        $this->assertContains('drive',$record['quick_services']);
    }
    public function test_it_does_not_confirm_halal_when_the_official_flag_is_absent(): void
    {
        $record=(new QuickRestaurantSource)->normalise(['attributes'=>['name'=>'Test','slug'=>'test']]);
        $this->assertSame('halal_not_found',$record['quick_halal_status']);
    }
}
