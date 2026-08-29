<?php
namespace App\Services\Geocoding;
use Illuminate\Support\Str;
class GeocodingConfidence {
 public function decide(array $feature, ?float $distance, ?string $derivedPostcode, ?string $derivedCity, bool $hasGps, bool $duplicateCluster, bool $geoConflict): array {
  if (!$feature) return ['status'=>'MISSING','reason'=>'no_provider_result']; $postcode=$feature['postcode']??null; $city=$feature['city']??null; $type=$feature['type']??null; $score=(float)($feature['score']??0); $cp=$derivedPostcode && $postcode ? $derivedPostcode===$postcode : null; $cityMatch=$derivedCity && $city ? $this->sameCity($derivedCity,$city) : null;
  $strong=$type==='housenumber' && $score>=.80 && $postcode && $city && $cp!==false && $cityMatch!==false && !$geoConflict;
  if ($hasGps && $distance!==null && $distance>1000) return ['status'=>'REVIEW_REQUIRED','reason'=>'gps_distance_over_1000m'];
  if ($strong && $hasGps && $distance!==null && $distance<=150) return ['status'=>$duplicateCluster?'HIGH_CONFIDENCE':'VERIFIED','reason'=>$duplicateCluster?'duplicate_cluster_confirmed':'address_gps_match'];
  if ($strong && !$hasGps) return ['status'=>'HIGH_CONFIDENCE','reason'=>'precise_address_without_gps'];
  if ($type==='street' || ($hasGps && $distance!==null && $distance<=1000)) return ['status'=>'APPROXIMATE','reason'=>'street_or_approximate_gps'];
  return ['status'=>'REVIEW_REQUIRED','reason'=>'insufficient_concordance'];
 }
 public function sameCity(string $a,string $b): bool { $n=fn($x)=>str_replace(['saint','st'],['st','st'],preg_replace('/[^a-z0-9]/','',Str::ascii(Str::lower($x)))); return $n($a)===$n($b); }
}
