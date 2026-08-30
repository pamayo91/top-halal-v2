<?php

namespace App\Services\WebEnrichment;

use App\Models\Restaurant;

interface RestaurantWebSourceProvider
{
    /** Returns normalized evidence only; it must never write to V2. */
    public function find(Restaurant $restaurant): array;
}
