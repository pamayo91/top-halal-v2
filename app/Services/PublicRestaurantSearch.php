<?php

namespace App\Services;

use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PublicRestaurantSearch
{
    public function published(): Builder
    {
        return Restaurant::where('status', 'published')->with(['categories', 'features', 'locations', 'openingHours', 'media.asset.variants', 'outboundLinks' => fn ($q) => $q->where('is_active', true)]);
    }

    public function apply(Builder $query, Request $request): Builder
    {
        if ($text = trim((string) $request->input('q'))) {
            $escaped = addcslashes(Str::lower($text), '%_\\');
            $query->where(fn (Builder $search) => $search->whereRaw('LOWER(name) LIKE ?', ["%{$escaped}%"])->orWhereRaw('LOWER(city_name) LIKE ?', ["%{$escaped}%"]));
        }
        if ($city = trim((string) $request->input('ville'))) {
            $query->where(fn (Builder $cities) => $cities->whereRaw('LOWER(city_name) = ?', [Str::lower(str_replace('-', ' ', $city))])->orWhereHas('locations', fn (Builder $locations) => $locations->where('slug', $city)));
        }
        foreach (array_filter((array) $request->input('categories', []), 'is_string') as $slug) $query->whereHas('categories', fn (Builder $q) => $q->where('slug', $slug));
        foreach (array_filter((array) $request->input('features', []), 'is_string') as $slug) $query->whereHas('features', fn (Builder $q) => $q->where('slug', $slug));
        if ($request->filled(['lat', 'lng'])) {
            $lat = (float) $request->input('lat'); $lng = (float) $request->input('lng');
            $clamp = DB::connection()->getDriverName() === 'sqlite' ? 'min' : 'least';
            $distance = "(6371 * acos({$clamp}(1, cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))))";
            $query->whereNotNull('latitude')->whereNotNull('longitude')->whereBetween('latitude', [-90, 90])->whereBetween('longitude', [-180, 180])->select('restaurants.*')->selectRaw("{$distance} as distance_km", [$lat, $lng, $lat])->orderBy('distance_km');
        } else $query->orderBy('name');
        return $query;
    }
}
