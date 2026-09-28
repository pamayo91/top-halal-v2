<?php

namespace App\Services;

use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PublicRestaurantSearch
{
    public function __construct(private readonly CityPageResolver $cities, private readonly CommuneDirectory $communes, private readonly RestaurantSearchState $state) {}

    public function published(): Builder
    {
        return Restaurant::where('status', 'published')->with(['categories', 'features', 'openingHours', 'media.asset.variants', 'outboundLinks' => fn ($q) => $q->where('is_active', true)]);
    }

    /** @param array{q:string,city_code:?string,ville:?string,categories:list<string>,features:list<string>,lat:?float,lng:?float,nearby:bool,radius_km:?int}|null $state */
    public function apply(Builder $query, Request $request, ?array $state = null): Builder
    {
        $state ??= $this->state->from($request);
        if ($text = $state['q']) {
            $escaped = addcslashes(Str::lower($text), '%_\\');
            $query->where(fn (Builder $search) => $search->whereRaw('LOWER(name) LIKE ?', ["%{$escaped}%"])->orWhereRaw('LOWER(city_name) LIKE ?', ["%{$escaped}%"]));
        }
        if ($cityCode = $state['city_code']) {
            $commune = $this->communes->find($cityCode);
            $query->when($commune !== null, fn (Builder $cities) => $cities->whereIn('city_code', $commune->source_city_codes), fn (Builder $cities) => $cities->whereRaw('1 = 0'));
        } elseif ($legacyCity = $state['ville']) {
            // Compatibility for existing result URLs; new forms always submit city_code.
            $cityPage = $this->cities->cityForSlug($legacyCity);
            $query->when($cityPage !== null, fn (Builder $cities) => $cities->whereIn('city_code', $cityPage->source_city_codes), fn (Builder $cities) => $cities->whereRaw('1 = 0'));
        }
        foreach ($state['categories'] as $slug) $query->whereHas('categories', fn (Builder $q) => $q->where('slug', $slug));
        foreach ($state['features'] as $slug) $query->whereHas('features', fn (Builder $q) => $q->where('slug', $slug));
        if ($state['nearby']) {
            $lat = $state['lat']; $lng = $state['lng']; $radius = $state['radius_km'];
            $clamp = DB::connection()->getDriverName() === 'sqlite' ? 'min' : 'least';
            $distance = "(6371 * acos({$clamp}(1, cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))))";
            $latitudeDelta = $radius / 111.045;
            $longitudeDelta = $radius / max(0.00001, 111.045 * abs(cos(deg2rad($lat))));
            $query->whereNotNull('latitude')->whereNotNull('longitude')->whereBetween('latitude', [-90, 90])->whereBetween('longitude', [-180, 180])
                ->whereBetween('latitude', [$lat - $latitudeDelta, $lat + $latitudeDelta]);
            if ($lng - $longitudeDelta >= -180 && $lng + $longitudeDelta <= 180) $query->whereBetween('longitude', [$lng - $longitudeDelta, $lng + $longitudeDelta]);
            $query->whereRaw("{$distance} <= ?", [$lat, $lng, $lat, $radius])
                ->select('restaurants.*')->selectRaw("{$distance} as distance_km", [$lat, $lng, $lat])->orderBy('distance_km');
        } else $this->orderByRecent($query);
        return $query;
    }

    /** @return array{q:string,city_code:?string,ville:?string,categories:list<string>,features:list<string>,lat:?float,lng:?float,nearby:bool,radius_km:?int} */
    public function state(Request $request): array { return $this->state->from($request); }

    /** @param array{q:string,city_code:?string,ville:?string,categories:list<string>,features:list<string>,lat:?float,lng:?float,nearby:bool,radius_km:?int} $state */
    public function queryParameters(array $state): array { return $this->state->query($state); }

    /** Apply the one canonical default order to every non-proximity public listing. */
    public function orderByRecent(Builder $query): Builder
    {
        // Restaurants do not have an independent published_at column. The
        // legacy publication timestamp is canonical; V2 records use creation.
        return $query->orderByRaw('COALESCE(legacy_published_at, created_at) DESC')->orderByDesc('id');
    }
}
