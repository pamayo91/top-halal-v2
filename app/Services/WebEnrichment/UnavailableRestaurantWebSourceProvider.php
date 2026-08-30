<?php

namespace App\Services\WebEnrichment;

use App\Models\Restaurant;

class UnavailableRestaurantWebSourceProvider implements RestaurantWebSourceProvider
{
    public function find(Restaurant $restaurant): array
    {
        return ['state'=>'unavailable','sources'=>[],'reason'=>'No configured web source provider. Set RESTAURANT_WEB_PROVIDER and its server-side credentials before running an applied batch.'];
    }
}
