<?php

namespace App\Policies;

use App\Models\Restaurant;
use App\Models\{RestaurantClaim, RestaurantSubmission};
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

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

        return $this->representedRestaurantsQuery($user)->whereKey($restaurant)->exists();
    }

    /**
     * Restaurants represented by this identity, excluding an administrator's
     * technical global access. Account-specific operations use this exact
     * policy query instead of rebuilding the authorization rules.
     */
    public function representedRestaurantsQuery(User $user): Builder
    {
        return Restaurant::query()->where(function (Builder $query) use ($user): void {
            $query->whereHas('claims', fn (Builder $claims) => $claims
                ->where('user_id', $user->id)
                ->where('status', 'approved'))
                ->orWhereHas('legacyAuthorships', fn (Builder $authorships) => $authorships
                    ->where('user_id', $user->id))
                ->orWhere(function (Builder $submissions) use ($user): void {
                    $submissions->whereHas('submission', fn (Builder $submission) => $submission
                        ->where('user_id', $user->id)
                        ->where('status', '!=', 'rejected'))
                        ->whereDoesntHave('claims', fn (Builder $claims) => $claims
                            ->where('status', 'approved'));
                });
        });
    }
}
