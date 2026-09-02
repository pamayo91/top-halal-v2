<?php

namespace App\Http\Controllers;

use App\Models\Restaurant;
use App\Services\Location\AddressSuggestionService;
use App\Services\Location\RestaurantLocationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OwnerRestaurantController extends Controller
{
    public function edit(Restaurant $restaurant): View
    {
        $this->authorize('manage', $restaurant);

        return view('account.restaurant-edit', compact('restaurant'));
    }

    public function update(Request $request, Restaurant $restaurant, AddressSuggestionService $suggestions, RestaurantLocationService $locations): RedirectResponse
    {
        $this->authorize('manage', $restaurant);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'phone' => ['nullable', 'string', 'max:50'],
            'location_changed' => ['nullable', 'boolean'],
            'address_suggestion_token' => ['required_if:location_changed,1', 'nullable', 'uuid'],
            'latitude' => ['nullable', 'required_if:map_moved,1', 'numeric', 'between:41,52'],
            'longitude' => ['nullable', 'required_if:map_moved,1', 'numeric', 'between:-5.5,10'],
            'map_moved' => ['nullable', 'boolean'],
        ]);
        $restaurant->update(collect($data)->only(['name', 'description', 'phone'])->all());

        if ($request->boolean('location_changed')) {
            $selection = $suggestions->structuredFromToken((string) $data['address_suggestion_token']);
            if ($selection === null) return back()->withErrors(['address_suggestion_token' => 'Cette suggestion a expiré. Recherchez l’adresse à nouveau.'])->withInput();
            $locations->applySelectedSuggestion(
                $restaurant,
                $selection,
                $request->boolean('map_moved') ? (float) $data['latitude'] : null,
                $request->boolean('map_moved') ? (float) $data['longitude'] : null,
                'owner_map',
            );
        }

        return back()->with('status', 'Restaurant mis à jour.');
    }
}
