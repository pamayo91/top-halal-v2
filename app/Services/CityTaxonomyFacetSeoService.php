<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;

abstract class CityTaxonomyFacetSeoService
{
    public function __construct(private readonly CityPageResolver $cities) {}

    abstract protected function pageModel(): string;
    abstract protected function termModel(): string;
    abstract protected function foreignKey(): string;
    abstract protected function routeName(): string;
    abstract public function defaults(object $city, Model $term, int $count): array;

    public function pageFor(object $city, Model $term): ?Model
    {
        $model = $this->pageModel();

        return $model::query()->where('city_code', $city->city_code)->where($this->foreignKey(), $term->getKey())->where('state', 'open')->first();
    }

    public function isOpen(object $city, Model $term): bool
    {
        return $this->pageFor($city, $term) !== null;
    }

    public function url(object $city, Model $term): string
    {
        return route($this->routeName(), ['city' => $city->slug, 'facet' => $term->slug]);
    }

    public function openedForCity(object $city): \Illuminate\Support\Collection
    {
        $termModel = $this->termModel();
        $pageModel = $this->pageModel();
        $term = (new $termModel)->getTable();
        $page = (new $pageModel)->getTable();
        $foreignKey = $this->foreignKey();

        return $termModel::query()->select("{$term}.*")
            ->join($page, "{$page}.{$foreignKey}", '=', "{$term}.id")
            ->where("{$page}.city_code", $city->city_code)
            ->where("{$page}.state", 'open')
            ->orderBy("{$term}.name")
            ->get();
    }

    public function cityForSlug(string $slug): ?object
    {
        return $this->cities->cityForSlug($slug);
    }

    public function forget(): void
    {
        // Sparse facet state is queried directly, so changes are immediate.
    }
}
