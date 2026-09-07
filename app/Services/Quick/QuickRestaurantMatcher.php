<?php

namespace App\Services\Quick;

use App\Models\Restaurant;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class QuickRestaurantMatcher
{
    public function normalise(?string $value, bool $stripQuick = false): string
    {
        $value = Str::ascii(Str::lower(trim((string) $value)));
        if ($stripQuick) $value = preg_replace('/^quick(?: restaurant)?(?:\s+hal+al)?\s*/', '', $value);
        return preg_replace('/[^a-z0-9]+/', '', $value) ?: '';
    }

    /** @param array<string,mixed> $quick @param Collection<int,Restaurant> $restaurants @return array{status:string,score:int,restaurant:?Restaurant,candidates:array<int,Restaurant>,differences:array<int,string>,actions:array<int,string>} */
    public function match(array $quick, Collection $restaurants): array
    {
        $candidates = $restaurants->filter(fn (Restaurant $restaurant) => $this->isPlausibleCandidate($quick, $restaurant))
            ->map(fn (Restaurant $restaurant) => ['restaurant' => $restaurant, 'score' => $this->score($quick, $restaurant)])
            ->filter(fn ($candidate) => $candidate['score'] >= 45)->sortByDesc('score')->values();
        $best = $candidates->first(); $restaurant = $best['restaurant'] ?? null; $score = $best['score'] ?? 0;
        $duplicates = $candidates->filter(fn ($candidate) => $candidate['score'] >= 75)->pluck('restaurant')->all();
        $differences = $restaurant ? $this->differences($quick, $restaurant) : [];

        if ($quick['quick_halal_status'] !== 'halal_confirmed') $status = 'HALAL_NOT_CONFIRMED';
        elseif (count($duplicates) > 1) $status = 'DUPLICATE_TOP_HALAL';
        elseif (! $restaurant || $score < 65) $status = 'MISSING_TOP_HALAL';
        elseif ($score >= 85 && $differences === []) $status = 'MATCH_EXACT';
        elseif ($score >= 85) $status = 'MATCH_UPDATE';
        else $status = 'MATCH_PROBABLE';

        return ['status'=>$status, 'score'=>$score, 'restaurant'=>$restaurant, 'candidates'=>$duplicates, 'differences'=>$differences,
            'actions'=>$status === 'MISSING_TOP_HALAL' ? ['CREATE_RESTAURANT'] : array_map(fn ($field) => 'UPDATE_'.strtoupper($field), $differences)];
    }

    /** @param array<string,mixed> $q */
    public function score(array $q, Restaurant $r): int
    {
        $score = 0;
        if ($this->normalise($q['quick_name'], true) === $this->normalise($r->name, true)) $score += 45;
        elseif (($this->same($q['quick_postal_code'], $r->postal_code) || $this->same($q['quick_city'], $r->city_name)) && $this->similar($q['quick_name'], $r->name) >= 85) $score += 25;
        if ($q['quick_postal_code'] && (string) $q['quick_postal_code'] === (string) $r->postal_code) $score += 20;
        if ($q['quick_city'] && $this->normalise($q['quick_city']) === $this->normalise($r->city_name)) $score += 15;
        if ($q['quick_address_line1'] && $this->normalise($q['quick_address_line1']) === $this->normalise($r->address_line1 ?: $r->address)) $score += 25;
        if ($q['quick_phone'] && $this->digits($q['quick_phone']) === $this->digits($r->phone)) $score += 10;
        if ($q['quick_latitude'] !== null && $r->latitude !== null) { $d=$this->distance((float)$q['quick_latitude'],(float)$q['quick_longitude'],(float)$r->latitude,(float)$r->longitude); $score += $d <= 75 ? 20 : ($d <= 250 ? 15 : ($d <= 1000 ? 5 : 0)); }
        return $score;
    }

    /** Discards unrelated rows before invoking the expensive fuzzy comparison. */
    private function isPlausibleCandidate(array $q, Restaurant $r): bool
    {
        return $this->normalise($q['quick_name'], true) === $this->normalise($r->name, true)
            || $this->same($q['quick_postal_code'], $r->postal_code)
            || $this->same($q['quick_city'], $r->city_name);
    }
    private function same(?string $left, ?string $right): bool { return $left !== null && $right !== null && $this->normalise($left) !== '' && $this->normalise($left) === $this->normalise($right); }

    /** @param array<string,mixed> $q @return array<int,string> */
    private function differences(array $q, Restaurant $r): array
    {
        $fields=['name'=>[$q['quick_name'],$r->name,true], 'address'=>[$q['quick_address_line1'],$r->address_line1 ?: $r->address,false], 'postal_code'=>[$q['quick_postal_code'],$r->postal_code,false], 'city'=>[$q['quick_city'],$r->city_name,false], 'phone'=>[$q['quick_phone'],$r->phone,false]];
        $out=[]; foreach($fields as $field=>[$quick,$top,$strip]) if ($quick && $top && $this->normalise($quick,$strip) !== $this->normalise($top,$strip)) $out[]=$field;
        if ($q['quick_latitude'] !== null && $r->latitude !== null && $this->distance((float)$q['quick_latitude'],(float)$q['quick_longitude'],(float)$r->latitude,(float)$r->longitude)>75) $out[]='coordinates';
        return $out;
    }
    private function similar(string $a,string $b): float { similar_text($this->normalise($a,true),$this->normalise($b,true),$percent); return $percent; }
    private function digits(?string $v): string { return preg_replace('/\D/', '', (string)$v) ?: ''; }
    private function distance(float $a,float $b,float $c,float $d): float { $x=sin(deg2rad($a))*sin(deg2rad($c))+cos(deg2rad($a))*cos(deg2rad($c))*cos(deg2rad($d-$b)); return 6371000*acos(min(1,max(-1,$x))); }
}
