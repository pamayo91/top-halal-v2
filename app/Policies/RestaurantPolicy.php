<?php

namespace App\Policies;

use App\Models\Restaurant;
use App\Models\{RestaurantClaim, RestaurantSubmission};
use App\Models\User;

class RestaurantPolicy
{
    public function manage(User $user, Restaurant $restaurant): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        if ($restaurant->legacyAuthorships()->where('user_id', $user->id)->exists()) {
            return true;
        }

        if (RestaurantClaim::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->exists()) {
            return true;
        }

        return ! RestaurantClaim::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('status', 'approved')
            ->exists()
            && RestaurantSubmission::query()
                ->where('restaurant_id', $restaurant->id)
                ->where('user_id', $user->id)
                ->exists();
    }
}
