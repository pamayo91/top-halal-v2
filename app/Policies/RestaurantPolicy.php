<?php

namespace App\Policies;

use App\Models\Restaurant;
use App\Models\{RestaurantClaim, RestaurantSubmission};
use App\Models\User;

class RestaurantPolicy
{
    /**
     * The exact restaurant-manager rule shared by owner features and review
     * eligibility. A depositor is deliberately not a manager here.
     */
    public function isRestaurantManager(User $user, Restaurant $restaurant): bool
    {
        return $restaurant->legacyAuthorships()->where('user_id', $user->id)->exists()
            || RestaurantClaim::query()
                ->where('restaurant_id', $restaurant->id)
                ->where('user_id', $user->id)
                ->where('status', 'approved')
                ->exists();
    }

    public function manage(User $user, Restaurant $restaurant): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        if ($this->isRestaurantManager($user, $restaurant)) {
            return true;
        }

        return ! RestaurantClaim::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('status', 'approved')
            ->exists()
            && RestaurantSubmission::query()
                ->where('restaurant_id', $restaurant->id)
                ->where('user_id', $user->id)
                ->where('status', '!=', 'rejected')
                ->exists();
    }
}
