<?php

namespace App\Support;

use App\Models\Restaurant;

class RestaurantSeo
{
    public static function title(Restaurant $restaurant): string
    {
        if (filled($restaurant->seo_title)) {
            return trim($restaurant->seo_title);
        }

        $title = 'Restaurant '.$restaurant->name.' Halal';
        $city = filled($restaurant->city_name) ? $restaurant->city_name : $restaurant->locations->sortBy('name')->first()?->name;
        $specialty = $restaurant->categories->sortBy('name')->first()?->name;

        if ($city) {
            $title .= ' à '.$city;
        }

        if ($specialty) {
            $title .= ' spécialité '.$specialty;
        }

        return $title;
    }
}
