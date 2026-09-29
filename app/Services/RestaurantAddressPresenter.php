<?php

namespace App\Services;

use App\Models\Restaurant;

class RestaurantAddressPresenter
{
    public function hasStructuredAddress(Restaurant $restaurant): bool
    {
        return filled($restaurant->address_line1)
            || filled($restaurant->postal_code)
            || filled($restaurant->city_name);
    }

    /** The public contact-card line, retaining its legacy fallback for old records. */
    public function primaryLine(Restaurant $restaurant): string
    {
        return $this->hasStructuredAddress($restaurant)
            ? (string) $restaurant->address_line1
            : (string) preg_replace('/,?\s*France\s*$/iu', '', (string) $restaurant->address);
    }

    public function localityLine(Restaurant $restaurant): string
    {
        return trim(implode(' ', array_filter([$restaurant->postal_code, $restaurant->city_name])));
    }

    /** Never falls back to the raw legacy address. */
    public function structuredLine(Restaurant $restaurant): string
    {
        return implode(', ', array_filter([$restaurant->address_line1, $this->localityLine($restaurant)]));
    }
}
