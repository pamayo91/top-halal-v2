<?php

namespace App\Http\Controllers;

use App\Models\Restaurant;
use App\Models\RestaurantClaim;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RestaurantClaimController extends Controller
{
    public function create(Restaurant $restaurant): View
    {
        return view('claims.create', ['restaurant' => $restaurant, 'claim' => $restaurant->claims()->where('user_id', request()->user()->id)->first()]);
    }

    public function store(Request $request, Restaurant $restaurant): RedirectResponse
    {
        $data = $request->validate(['message' => ['nullable', 'string', 'max:1000']]);
        $claim = RestaurantClaim::firstOrCreate(
            ['restaurant_id' => $restaurant->id, 'user_id' => $request->user()->id],
            ['status' => 'pending', 'message' => $data['message'] ?? null, 'submitted_at' => now()],
        );

        if (! $claim->wasRecentlyCreated) {
            return back()->withErrors(['claim' => 'Une demande existe déjà pour ce restaurant.']);
        }

        return redirect()->route('claims.show', $claim)->with('status', 'Demande envoyée pour modération.');
    }

    public function show(RestaurantClaim $claim): View
    {
        abort_unless($claim->user_id === request()->user()->id || request()->user()->role === 'admin', 403);

        return view('claims.show', compact('claim'));
    }
}
