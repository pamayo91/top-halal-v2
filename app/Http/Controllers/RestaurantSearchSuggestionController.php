<?php

namespace App\Http\Controllers;

use App\Models\{Category, Restaurant};
use App\Services\CityPageResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RestaurantSearchSuggestionController extends Controller
{
    public function __construct(private readonly CityPageResolver $cities) {}

    public function cities(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q'));
        $normalizedTerm = Str::lower($term);
        $cities = $this->cities->cities()
            ->filter(fn (object $city): bool => $normalizedTerm === '' || str_contains(Str::lower($city->city_name), $normalizedTerm))
            ->sortByDesc('restaurants_count')
            ->take(12)
            ->map(fn (object $city): array => [
                'name' => $city->city_name.($city->is_ambiguous ? ' — '.$city->department['name'] : ''),
                'slug' => $city->slug,
                'count' => $city->restaurants_count,
            ]);
        return response()->json(['cities' => $cities->sortByDesc(fn ($city) => $city['slug'] === 'paris')->values()]);
    }

    public function suggestions(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q'));
        if (Str::length($term) < 2) return response()->json(['specialties' => [], 'restaurants' => []]);
        $escaped = addcslashes(Str::lower($term), '%_\\');
        $city = $this->cities->cityForSlug(trim((string) $request->query('ville')));
        // A specialty remains selectable as soon as it exists in the V2
        // catalogue, including before its first published restaurant.
        $specialties = Category::query()->whereRaw('LOWER(name) LIKE ?', ["%{$escaped}%"])->orderBy('name')->limit(5)->get(['name', 'slug']);
        $restaurants = Restaurant::query()->where('status', 'published')->whereRaw('LOWER(name) LIKE ?', ["%{$escaped}%"])
            ->when($city !== null, fn (Builder $q) => $q->orderByRaw('CASE WHEN city_code IN ('.implode(',', array_fill(0, count($city->source_city_codes), '?')).') THEN 0 ELSE 1 END', $city->source_city_codes))
            ->orderBy('name')->limit(6)->get(['name', 'slug', 'city_name']);
        return response()->json(['specialties' => $specialties, 'restaurants' => $restaurants]);
    }
}
