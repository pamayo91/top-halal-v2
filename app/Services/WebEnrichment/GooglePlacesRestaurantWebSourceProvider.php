<?php

namespace App\Services\WebEnrichment;

use App\Models\Restaurant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/** Optional official Google Places API adapter. No Google HTML is scraped. */
class GooglePlacesRestaurantWebSourceProvider implements RestaurantWebSourceProvider
{
    public function find(Restaurant $restaurant): array
    {
        $query = trim(implode(', ', array_filter([$restaurant->name, $restaurant->address_line1 ?: $restaurant->address, $restaurant->postal_code, $restaurant->city_name])));
        $key = 'restaurant-web:google:'.hash('sha256', $query);
        if ($cached = Cache::get($key)) return $cached;
        try {
            $response = Http::acceptJson()->withHeaders(['X-Goog-Api-Key'=>config('services.restaurant_web.google_places_key'),'X-Goog-FieldMask'=>'places.id,places.displayName,places.formattedAddress,places.businessStatus,places.regularOpeningHours,places.types,places.delivery,places.takeout,places.dineIn,places.googleMapsUri'])
                ->timeout((int) config('services.restaurant_web.timeout', 8))->retry(1, 400, throw: false)
                ->post('https://places.googleapis.com/v1/places:searchText', ['textQuery'=>$query,'languageCode'=>'fr','regionCode'=>'FR','maxResultCount'=>3]);
            if (!$response->successful()) return $this->cache($key, ['state'=>'error','sources'=>[],'reason'=>'Google Places HTTP '.$response->status()]);
            $places = $response->json('places', []);
            $place = collect($places)->map(fn ($p) => [$p, $this->score($restaurant, $p)])->sortByDesc(1)->first();
            if (!$place || $place[1] < 0.78) return $this->cache($key, ['state'=>'unmatched','sources'=>[],'reason'=>'No Google Places result matched both name and location']);
            [$p, $score] = $place; $source = $p['googleMapsUri'] ?? ('google-places:'.($p['id'] ?? 'unknown'));
            $status = $p['businessStatus'] ?? null;
            $hours = $this->hours($p['regularOpeningHours']['periods'] ?? []);
            return $this->cache($key, ['state'=>'matched','confidence'=>(int) round($score * 100),'sources'=>[$source],'closure'=>$status === 'CLOSED_PERMANENTLY' ? 'confirmed' : null,'closure_sources'=>$status === 'CLOSED_PERMANENTLY' ? [$source] : [],'hours'=>$hours,'hours_source'=>$hours ? $source : null,'description'=>$this->description($restaurant, $p),'description_sources'=>[$source]]);
        } catch (\Throwable $e) { return $this->cache($key, ['state'=>'error','sources'=>[],'reason'=>class_basename($e)]); }
    }

    private function score(Restaurant $restaurant, array $place): float
    {
        $name = $this->similar($restaurant->name, data_get($place, 'displayName.text', ''));
        $address = $this->similar(implode(' ', array_filter([$restaurant->address_line1 ?: $restaurant->address, $restaurant->postal_code, $restaurant->city_name])), $place['formattedAddress'] ?? '');
        return ($name * .60) + ($address * .40);
    }
    private function similar(string $a, string $b): float { $a = $this->normal($a); $b = $this->normal($b); similar_text($a, $b, $percent); return $a === '' || $b === '' ? 0 : $percent / 100; }
    private function normal(string $value): string { return preg_replace('/[^a-z0-9]+/u', ' ', str_replace(['é','è','ê','à','ç','ù','ï'], ['e','e','e','a','c','u','i'], mb_strtolower($value))) ?: ''; }
    private function hours(array $periods): array
    {
        $days = ['SUNDAY'=>'sunday','MONDAY'=>'monday','TUESDAY'=>'tuesday','WEDNESDAY'=>'wednesday','THURSDAY'=>'thursday','FRIDAY'=>'friday','SATURDAY'=>'saturday']; $result=[];
        foreach ($periods as $period) { $open=$period['open'] ?? []; $close=$period['close'] ?? []; if (!isset($days[$open['day'] ?? '']) || !isset($open['hour'], $open['minute'], $close['hour'], $close['minute'])) continue; $result[]=['day'=>$days[$open['day']],'opens_at'=>sprintf('%02d:%02d:00',$open['hour'],$open['minute']),'closes_at'=>sprintf('%02d:%02d:00',$close['hour'],$close['minute']),'is_closed'=>false]; }
        return $result;
    }
    private function description(Restaurant $restaurant, array $place): ?string
    {
        $types = collect($place['types'] ?? [])->map(fn ($t) => str_replace('_', ' ', $t)); $cuisine = $types->first(fn ($t) => str_contains($t, 'restaurant') || str_contains($t, 'food'));
        $facts=[]; if ($restaurant->city_name && $cuisine) $facts[]='Restaurant '.strtolower($cuisine).' situé à '.$restaurant->city_name.'.';
        $services=[]; foreach (['takeout'=>'vente à emporter','delivery'=>'livraison','dineIn'=>'service sur place'] as $key=>$label) if (($place[$key] ?? false) === true) $services[]=$label;
        if ($services) $facts[]='L’établissement propose '.implode(', ', $services).'.';
        return count($facts) >= 2 ? implode(' ', $facts) : null;
    }
    private function cache(string $key, array $result): array { Cache::put($key, $result, now()->addDays(14)); usleep((int) config('services.restaurant_web.rate_sleep_us', 500000)); return $result; }
}
