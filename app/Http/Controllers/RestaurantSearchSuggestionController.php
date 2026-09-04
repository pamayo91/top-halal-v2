<?php

namespace App\Http\Controllers;

use App\Models\{Category, Restaurant};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RestaurantSearchSuggestionController extends Controller
{
    public function cities(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q'));
        $cities = Restaurant::query()->where('status', 'published')->whereNotNull('city_name')->where('city_name', '!=', '')
            ->when($term !== '', fn (Builder $q) => $q->whereRaw('LOWER(city_name) LIKE ?', ['%'.addcslashes(Str::lower($term), '%_\\').'%']))
            ->selectRaw('city_name, count(*) as restaurants_count')->groupBy('city_name')->orderByDesc('restaurants_count')->orderBy('city_name')->limit(12)->get()
            ->map(fn ($city) => ['name' => $city->city_name, 'slug' => Str::slug($city->city_name), 'count' => (int) $city->restaurants_count]);
        return response()->json(['cities' => $cities->sortByDesc(fn ($city) => $city['slug'] === 'paris')->values()]);
    }

    public function suggestions(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q'));
        if (Str::length($term) < 2) return response()->json(['specialties' => [], 'restaurants' => []]);
        $escaped = addcslashes(Str::lower($term), '%_\\');
        $city = trim((string) $request->query('ville'));
        // A specialty remains selectable as soon as it exists in the V2
        // catalogue, including before its first published restaurant.
        $specialties = Category::query()->whereRaw('LOWER(name) LIKE ?', ["%{$escaped}%"])->orderBy('name')->limit(5)->get(['name', 'slug']);
        $restaurants = Restaurant::query()->where('status', 'published')->whereRaw('LOWER(name) LIKE ?', ["%{$escaped}%"])
            ->when($city !== '', fn (Builder $q) => $q->orderByRaw('CASE WHEN LOWER(city_name) = ? THEN 0 ELSE 1 END', [Str::lower(str_replace('-', ' ', $city))]))
            ->orderBy('name')->limit(6)->get(['name', 'slug', 'city_name']);
        return response()->json(['specialties' => $specialties, 'restaurants' => $restaurants]);
    }
}
