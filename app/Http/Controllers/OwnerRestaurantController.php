<?php

namespace App\Http\Controllers;

use App\Models\Restaurant;
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

    public function update(Request $request, Restaurant $restaurant): RedirectResponse
    {
        $this->authorize('manage', $restaurant);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);
        $restaurant->update($data);

        return back()->with('status', 'Restaurant mis à jour.');
    }
}
