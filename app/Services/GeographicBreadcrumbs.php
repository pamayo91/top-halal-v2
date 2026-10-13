<?php

namespace App\Services;

use App\Models\Restaurant;

/**
 * The only public breadcrumb builder for administrative geography.
 *
 * Each URL comes from the resolved administrative identity (region code,
 * department code, or INSEE city code), never from a display label.
 */
class GeographicBreadcrumbs
{
    public function __construct(
        private readonly AdministrativeGeography $administrative,
        private readonly CityPageResolver $cities,
        private readonly GeographicPageResolver $geography,
    ) {}

    /** @return list<array{label:string,url:?string}> */
    public function forRestaurant(Restaurant $restaurant): array
    {
        $city = $this->cities->cityForCode((string) $restaurant->city_code);

        return $city === null
            ? [...$this->base(), ['label' => $restaurant->name, 'url' => null]]
            : [...$this->forCity($city, current: false), ['label' => $restaurant->name, 'url' => null]];
    }

    /** @return list<array{label:string,url:?string}> */
    public function forCity(object $city, bool $current = true): array
    {
        $region = $this->geography->regionForCode($city->region['code']);
        $department = $this->geography->departmentForCode($city->department['code']);

        // A city is built from the same local administrative reference, so a
        // missing page would indicate an invariant failure rather than a URL
        // fallback opportunity.
        if ($region === null || $department === null) {
            throw new \LogicException('Published city has no resolved administrative landing page.');
        }

        $items = [...$this->base(), ['label' => $region->name, 'url' => $this->locationUrl($region)]];

        if ($this->includesDepartment($city, $department, $region)) {
            $items[] = ['label' => $department->name, 'url' => $this->locationUrl($department)];
        }

        $items[] = ['label' => $city->city_name, 'url' => $current ? null : $this->locationUrl($city)];

        return $items;
    }

    /** @return list<array{label:string,url:?string}> */
    public function forCityFacet(object $city, object $term): array
    {
        return [...$this->forCity($city, current: false), ['label' => $term->name, 'url' => null]];
    }

    /** @return list<array{label:string,url:?string}> */
    public function forDepartment(object $department): array
    {
        $region = $this->geography->regionForCode($department->region['code']);
        if ($region === null) {
            throw new \LogicException('Published department has no resolved regional landing page.');
        }

        $items = $this->base();
        if ($this->hasDistinctRegionalLanding($department, $region)) {
            $items[] = ['label' => $region->name, 'url' => $this->locationUrl($region)];
        }
        $items[] = ['label' => $department->name, 'url' => null];

        return $items;
    }

    /** @return list<array{label:string,url:?string}> */
    public function forRegion(object $region): array
    {
        return [...$this->base(), ['label' => $region->name, 'url' => null]];
    }

    /** @return list<array{label:string,url:?string}> */
    private function base(): array
    {
        return [
            ['label' => 'Accueil', 'url' => route('home')],
            ['label' => 'Restaurants', 'url' => route('restaurants.index')],
        ];
    }

    private function includesDepartment(object $city, object $department, object $region): bool
    {
        return ! $this->administrative->cityAndDepartmentShareTerritory($city->city_code, $department->code)
            && ! $this->departmentAndRegionShareTerritory($department, $region)
            && $this->hasDistinctRegionalLanding($department, $region);
    }

    private function departmentAndRegionShareTerritory(object $department, object $region): bool
    {
        return count($region->department_codes) === 1
            && $region->department_codes[0] === $department->code
            && $region->name === $department->name;
    }

    private function hasDistinctRegionalLanding(object $department, object $region): bool
    {
        return $department->slug !== $region->slug;
    }

    private function locationUrl(object $page): string
    {
        return route('cities.show', $page->slug);
    }
}
