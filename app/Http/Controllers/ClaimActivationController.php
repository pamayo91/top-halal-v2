<?php

namespace App\Http\Controllers;

use App\Models\RestaurantClaim;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ClaimActivationController extends Controller
{
    public function create(RestaurantClaim $claim, string $token): View|RedirectResponse
    {
        abort_unless($this->valid($claim, $token), 404);
        if (! $claim->user_id && ($existing = \App\Models\User::query()->where('email', $claim->email)->first())) {
            $claim->update(['user_id' => $existing->id, 'activation_token' => null, 'activation_expires_at' => null]);
            if ($existing->role === 'user') $existing->update(['role' => 'restaurant_owner']);
            return redirect()->route('login')->with('status', 'Votre restaurant est rattaché à votre espace. Connectez-vous pour le gérer.');
        }
        return view('claims.activate', compact('claim', 'token'));
    }

    public function store(Request $request, RestaurantClaim $claim, string $token): RedirectResponse
    {
        abort_unless($this->valid($claim, $token), 404);
        $data = $request->validate(['password' => ['required', 'confirmed', 'min:12']]);
        $claim->user->forceFill(['password' => Hash::make($data['password']), 'must_change_password' => false, 'status' => 'active', 'email_verified_at' => now()])->save();
        $claim->update(['activation_token' => null, 'activation_expires_at' => null]);
        return redirect()->route('login')->with('status', 'Votre espace restaurateur est activé. Vous pouvez vous connecter.');
    }

    private function valid(RestaurantClaim $claim, string $token): bool
    {
        return $claim->status === 'approved' && $claim->activation_token
            && $claim->activation_expires_at?->isFuture()
            && hash_equals($claim->activation_token, hash('sha256', $token));
    }
}
