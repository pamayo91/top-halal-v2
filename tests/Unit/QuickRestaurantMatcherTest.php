<?php

namespace Tests\Unit;

use App\Models\Restaurant;
use App\Services\Quick\QuickRestaurantMatcher;
use Illuminate\Support\Collection;
use Tests\TestCase;

class QuickRestaurantMatcherTest extends TestCase
{
    private function quick(array $overrides=[]): array { return $overrides + ['quick_name'=>'Quick Paris Opéra','quick_address_line1'=>'10, Rue de l’Opéra','quick_postal_code'=>'75001','quick_city'=>'Paris','quick_phone'=>'01 02 03 04 05','quick_latitude'=>48.87,'quick_longitude'=>2.33,'quick_halal_status'=>'halal_confirmed']; }
    private function restaurant(array $overrides=[]): Restaurant { return new Restaurant($overrides + ['id'=>1,'name'=>'Quick Restaurant Paris Opera','address_line1'=>'10 rue de l Opera','postal_code'=>'75001','city_name'=>'PARIS','phone'=>'0102030405','latitude'=>48.87,'longitude'=>2.33]); }

    public function test_it_normalises_accents_punctuation_and_quick_prefixes(): void
    {
        $matcher=new QuickRestaurantMatcher;
        $this->assertSame('parisopera',$matcher->normalise(' Quick Restaurant Paris-Opéra ',true));
    }
    public function test_it_detects_an_exact_match_despite_address_formatting(): void { $this->assertSame('MATCH_EXACT',(new QuickRestaurantMatcher)->match($this->quick(),collect([$this->restaurant()]))['status']); }
    public function test_it_detects_an_update_for_a_strong_match_with_a_changed_name(): void
    {
        $result=(new QuickRestaurantMatcher)->match($this->quick(['quick_name'=>'Quick Paris Opéra Centre']),collect([$this->restaurant()]));
        $this->assertSame('MATCH_UPDATE',$result['status']); $this->assertContains('name',$result['differences']);
    }
    public function test_it_keeps_a_lower_confidence_match_as_probable(): void
    {
        $restaurant=$this->restaurant(['city_name'=>'Lyon','address_line1'=>'99 avenue Exemple']);
        $this->assertSame('MATCH_PROBABLE',(new QuickRestaurantMatcher)->match($this->quick(['quick_latitude'=>null,'quick_longitude'=>null]),collect([$restaurant]))['status']);
    }
    public function test_it_marks_absent_restaurants_without_a_candidate(): void { $this->assertSame('MISSING_TOP_HALAL',(new QuickRestaurantMatcher)->match($this->quick(),new Collection)['status']); }
    public function test_it_flags_several_strong_candidates_as_duplicates(): void
    {
        $this->assertSame('DUPLICATE_TOP_HALAL',(new QuickRestaurantMatcher)->match($this->quick(),collect([$this->restaurant(['id'=>1]),$this->restaurant(['id'=>2])]))['status']);
    }
}
