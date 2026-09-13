<?php

namespace App\Http\Controllers;

use App\Models\RestaurantClaim;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\DB;
use App\Notifications\ClaimLifecycleNotification;
use Illuminate\View\View;

class ClaimActivationController extends Controller
{
    public function create(RestaurantClaim $claim, string $token): View|RedirectResponse
    {
        abort_unless($this->matches($claim, $token), 404);
        if ($this->wasActivated($claim, $token)) return view('public.expiring-link-status', ['eyebrow' => 'Activation espace restaurateur', 'title' => 'Votre espace est déjà activé.', 'message' => 'Ce lien a déjà été utilisé. Connectez-vous pour continuer.', 'resendUrl' => null, 'exitUrl' => route('login'), 'exitLabel' => 'Se connecter']);
        if (! $claim->activation_expires_at?->isFuture() && $this->eligible($claim)) return view('public.expiring-link-status', ['eyebrow' => 'Activation espace restaurateur', 'title' => 'Ce lien a expiré.', 'message' => 'Vous pouvez recevoir un nouveau lien pour continuer.', 'resendUrl' => route('claims.activate.resend', [$claim, $token])]);
        if (! $this->eligible($claim)) return view('public.expiring-link-status', ['eyebrow' => 'Activation espace restaurateur', 'title' => 'Cette demande a déjà été traitée.', 'message' => 'Cet espace ne peut plus être activé avec ce lien.', 'resendUrl' => null]);
        if (! $claim->user_id && ($existing = \App\Models\User::query()->where('email', $claim->email)->first())) {
            $claim->update(['user_id' => $existing->id, 'activation_expires_at' => now()]);
            return redirect()->route('login')->with('status', 'Votre restaurant est rattaché à votre espace. Connectez-vous pour le gérer.');
        }
        return view('claims.activate', compact('claim', 'token'));
    }

    public function store(Request $request, RestaurantClaim $claim, string $token): RedirectResponse
    {
        abort_unless($this->valid($claim, $token), 404);
        $data = $request->validate(['password' => ['required', 'confirmed', 'min:12']]);
        $claim->user->forceFill(['password' => Hash::make($data['password']), 'must_change_password' => false, 'status' => 'active', 'email_verified_at' => now()])->save();
        $claim->update(['activation_expires_at' => now()]);
        return redirect()->route('login')->with('status', 'Votre espace restaurateur est activé. Vous pouvez vous connecter.');
    }

    private function valid(RestaurantClaim $claim, string $token): bool
    {
        return $this->eligible($claim)
            && $claim->activation_expires_at?->isFuture()
            && $this->matches($claim, $token);
    }
    public function resend(RestaurantClaim $claim, string $token): RedirectResponse
    {
        [$claim, $newToken, $recipient] = DB::transaction(function () use ($claim, $token): array {
            $claim = RestaurantClaim::query()->with('user')->lockForUpdate()->findOrFail($claim->id);
            abort_unless($this->matches($claim, $token) && ! $claim->activation_expires_at?->isFuture() && $this->eligible($claim), 404);
            $newToken = Str::random(64);
            $claim->update(['activation_token' => hash('sha256', $newToken), 'activation_expires_at' => now()->addDays(7)]);
            return [$claim->fresh('restaurant'), $newToken, $claim->user];
        });
        $recipient->notify(new ClaimLifecycleNotification($claim, 'activate', URL::temporarySignedRoute('claims.activate', $claim->activation_expires_at, ['claim' => $claim, 'token' => $newToken])));
        return back()->with('status', 'Un nouveau lien vient d’être envoyé.');
    }
    private function matches(RestaurantClaim $claim, string $token): bool { return filled($claim->activation_token) && hash_equals($claim->activation_token, hash('sha256', $token)); }
    private function eligible(RestaurantClaim $claim): bool { return $claim->status === 'approved' && $claim->user?->status === 'active' && (! $claim->user?->login_enabled || $claim->user?->must_change_password); }
    private function wasActivated(RestaurantClaim $claim, string $token): bool { return $claim->status === 'approved' && $this->matches($claim, $token) && $claim->user?->status === 'active' && ! $claim->user?->must_change_password; }
}
