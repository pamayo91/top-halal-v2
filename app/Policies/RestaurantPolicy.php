<?php

namespace App\Policies;

use App\Models\Restaurant;
use App\Models\RestaurantClaim;
use App\Models\User;

class RestaurantPolicy
{
    public function manage(User $user, Restaurant $restaurant): bool
    {
        return $user->role === 'admin' || RestaurantClaim::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->exists();
    }
}
