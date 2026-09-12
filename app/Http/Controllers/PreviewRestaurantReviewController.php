<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRestaurantReviewRequest;
use App\Models\Restaurant;
use App\Services\ContributionIdentityService;
use Illuminate\Http\RedirectResponse;

class PreviewRestaurantReviewController extends Controller
{
    public function store(StoreRestaurantReviewRequest $request, int $legacyId, ContributionIdentityService $identities): RedirectResponse
    {
        $restaurant = Restaurant::where('legacy_wp_id', $legacyId)->where('status', 'published')->firstOrFail();
        $result = $identities->submitReview($request, $restaurant, $request->validated());

        return back()->with($result['verified'] ? 'review_submitted' : 'contribution_verification_sent', true);
    }
}
