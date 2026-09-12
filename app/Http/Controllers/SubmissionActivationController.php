<?php

namespace App\Http\Controllers;

use App\Models\RestaurantSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Hash};
use Illuminate\View\View;

class SubmissionActivationController extends Controller
{
    public function create(RestaurantSubmission $submission, string $token): View|RedirectResponse
    {
        if ($this->wasActivated($submission, $token)) {
            return view('public.restaurant-submission.already-activated');
        }

        abort_unless($this->valid($submission, $token), 404);

        if (! $submission->user?->must_change_password) {
            $submission->update(['activation_token' => null, 'activation_expires_at' => null]);

            return redirect()->route('login')->with('status', 'Cette fiche est rattachée à votre espace. Connectez-vous pour la gérer.');
        }

        return view('public.restaurant-submission.activate', compact('submission', 'token'));
    }

    public function store(Request $request, RestaurantSubmission $submission, string $token): RedirectResponse
    {
        abort_unless($this->valid($submission, $token), 404);
        $data = $request->validate(['password' => ['required', 'confirmed', 'min:12']]);

        $submission->user->forceFill([
            'password' => Hash::make($data['password']),
            'must_change_password' => false,
            'status' => 'active',
            'email_verified_at' => now(),
        ])->save();
        $submission->update(['activation_token' => null, 'activation_expires_at' => null]);
        Auth::login($submission->user);
        $request->session()->regenerate();

        return redirect()->route('account.dashboard')->with('status', 'Votre espace est activé. Vous pouvez gérer votre fiche.');
    }

    private function valid(RestaurantSubmission $submission, string $token): bool
    {
        return $submission->status !== 'pending_email_verification'
            && $submission->user_id
            && $submission->activation_token
            && $submission->activation_expires_at?->isFuture()
            && hash_equals($submission->activation_token, hash('sha256', $token));
    }

    private function wasActivated(RestaurantSubmission $submission, string $token): bool
    {
        return $submission->activation_token === null
            && $submission->user?->status === 'active'
            && ! $submission->user->must_change_password
            && preg_match('/^[A-Za-z0-9]{64}$/D', $token) === 1;
    }
}
