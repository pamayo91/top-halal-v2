<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRestaurantReviewRequest;
use App\Models\{Restaurant, RestaurantReview};
use Illuminate\Http\RedirectResponse;

class PreviewRestaurantReviewController extends Controller
{
    public function store(StoreRestaurantReviewRequest $request, int $legacyId): RedirectResponse
    {
        $restaurant = Restaurant::where('legacy_wp_id', $legacyId)->firstOrFail();
        RestaurantReview::create(['restaurant_id' => $restaurant->id, 'author_name' => $request->validated('name'), 'author_email' => $request->validated('email'), 'rating' => $request->validated('rating'), 'content' => trim(strip_tags($request->validated('content'))), 'status' => 'pending']);
        return back()->with('review_submitted', true);
    }
}
