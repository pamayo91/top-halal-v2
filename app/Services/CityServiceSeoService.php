<?php

namespace App\Services;

use App\Models\{CityServiceSeoPage, Feature};
use Illuminate\Database\Eloquent\Model;

class CityServiceSeoService extends CityTaxonomyFacetSeoService
{
    protected function pageModel(): string { return CityServiceSeoPage::class; }
    protected function termModel(): string { return Feature::class; }
    protected function foreignKey(): string { return 'feature_id'; }
    protected function routeName(): string { return 'city-specialties.show'; }

    public function defaults(object $city, Model $term, int $count): array
    {
        $service = mb_strtolower($term->name);
        $cityName = $city->city_name;

        return [
            'h1' => "Restaurants halal avec {$service} à {$cityName}",
            'title' => "Restaurants halal avec {$service} à {$cityName} | Top Halal",
            'description' => "Découvrez {$count} restaurant".($count > 1 ? 's' : '')." halal avec {$service} à {$cityName}.",
        ];
    }
}
