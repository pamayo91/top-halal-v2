<?php

namespace App\Services;

use App\Models\{Category, CitySpecialtySeoPage};
use Illuminate\Database\Eloquent\Model;

class CitySpecialtySeoService extends CityTaxonomyFacetSeoService
{
    protected function pageModel(): string { return CitySpecialtySeoPage::class; }
    protected function termModel(): string { return Category::class; }
    protected function foreignKey(): string { return 'category_id'; }
    protected function routeName(): string { return 'city-specialties.show'; }

    public function defaults(object $city, Model $category, int $count): array
    {
        $specialty = $category->name;
        $cityName = $city->city_name;

        return [
            'h1' => "Restaurants {$specialty} halal à {$cityName}",
            'title' => "Restaurants {$specialty} halal à {$cityName} | Top Halal",
            'description' => "Découvrez {$count} restaurant".($count > 1 ? 's' : '')." {$specialty} halal à {$cityName}.",
        ];
    }

}
