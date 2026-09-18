<?php

namespace App\Http\Controllers;

use App\Models\Restaurant;
use App\Models\RestaurantClaim;
use App\Models\RestaurantSubmission;
use App\Models\Category;
use App\Models\Feature;
use App\Http\Requests\UpdateManagedRestaurantRequest;
use App\Services\ManagedRestaurantUpdater;
use App\Services\RestaurantHours;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class OwnerRestaurantController extends Controller
{
    public function edit(Restaurant $restaurant): View|RedirectResponse
    {
        if (! request()->user()->can('manage', $restaurant)) {
            return redirect()->route('owner.restaurants.management-unavailable', $restaurant);
        }

        $restaurant->load([
            'categories',
            'features',
            'openingHours',
            'media.asset',
            'outboundLinks',
        ]);

        return view('account.restaurant-edit', [
            'restaurant' => $restaurant,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'features' => Feature::query()->orderBy('name')->get(['id', 'name']),
            'hours' => app(RestaurantHours::class)->editorState($restaurant->openingHours),
        ]);
    }

    public function managementUnavailable(Restaurant $restaurant): View|RedirectResponse
    {
        if (request()->user()->can('manage', $restaurant)) {
            return redirect()->route('owner.restaurants.edit', $restaurant);
        }

        $managementTransferred = RestaurantSubmission::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('user_id', request()->user()->id)
            ->exists()
            && RestaurantClaim::query()
                ->where('restaurant_id', $restaurant->id)
                ->where('status', 'approved')
                ->where('user_id', '!=', request()->user()->id)
                ->exists();

        return view('account.restaurant-management-unavailable', compact('restaurant', 'managementTransferred'));
    }

    public function update(UpdateManagedRestaurantRequest $request, Restaurant $restaurant, ManagedRestaurantUpdater $updater): RedirectResponse
    {
        if (! $request->user()->can('manage', $restaurant)) {
            return redirect()->route('owner.restaurants.management-unavailable', $restaurant);
        }
        $updater->update($restaurant, $request->validated(), $request);

        return back()->with('status', 'Restaurant mis à jour.');
    }
}
