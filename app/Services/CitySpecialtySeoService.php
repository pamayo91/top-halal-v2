<?php

namespace App\Services;

use App\Models\{Category, CitySpecialtySeoPage};

class CitySpecialtySeoService
{
    public function __construct(private readonly CityPageResolver $cities) {}

    public function pageFor(object $city, Category $category): ?CitySpecialtySeoPage
    {
        return CitySpecialtySeoPage::query()
            ->where('city_code', $city->city_code)
            ->where('category_id', $category->id)
            ->where('state', 'open')
            ->first();
    }

    public function isOpen(object $city, Category $category): bool
    {
        return $this->pageFor($city, $category) !== null;
    }

    public function url(object $city, Category $category): string
    {
        return route('city-specialties.show', ['city' => $city->slug, 'specialty' => $category->slug]);
    }

    public function openedForCity(object $city): \Illuminate\Support\Collection
    {
        return Category::query()
            ->select('categories.*')
            ->join('city_specialty_seo_pages', 'city_specialty_seo_pages.category_id', '=', 'categories.id')
            ->where('city_specialty_seo_pages.city_code', $city->city_code)
            ->where('city_specialty_seo_pages.state', 'open')
            ->orderBy('categories.name')
            ->get();
    }

    public function defaults(object $city, Category $category, int $count): array
    {
        $specialty = $category->name;
        $cityName = $city->city_name;

        return [
            'h1' => "Restaurants {$specialty} halal à {$cityName}",
            'title' => "Restaurants {$specialty} halal à {$cityName} | Top Halal",
            'description' => "Découvrez {$count} restaurant".($count > 1 ? 's' : '')." {$specialty} halal à {$cityName}.",
        ];
    }

    public function cityForSlug(string $slug): ?object
    {
        return $this->cities->cityForSlug($slug);
    }

    public function forget(): void
    {
        // Public facet resolution intentionally queries its sparse configuration
        // directly: a configuration change is visible immediately without stale
        // cache invalidation or a generated city × specialty matrix.
    }
}
