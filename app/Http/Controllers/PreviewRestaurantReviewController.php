<?php

namespace App\Http\Controllers;

use App\Exceptions\RestaurantReviewOwnershipException;
use App\Http\Requests\StoreRestaurantReviewRequest;
use App\Models\Restaurant;
use App\Services\ContributionIdentityService;
use Illuminate\Http\RedirectResponse;

class PreviewRestaurantReviewController extends Controller
{
    public function store(StoreRestaurantReviewRequest $request, int $legacyId, ContributionIdentityService $identities): RedirectResponse
    {
        $restaurant = Restaurant::where('legacy_wp_id', $legacyId)->where('status', 'published')->firstOrFail();
        try {
            $result = $identities->submitReview($request, $restaurant, $request->validated());
        } catch (RestaurantReviewOwnershipException) {
            return back()->with('review_ownership_forbidden', true);
        }

        return back()->with($result['verified'] ? 'review_submitted' : 'contribution_verification_sent', true);
    }
}
