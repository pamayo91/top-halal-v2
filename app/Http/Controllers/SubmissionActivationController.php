<?php

namespace App\Http\Controllers;

use App\Models\RestaurantSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Hash};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\RestaurantSubmissionMailer;
use Illuminate\View\View;

class SubmissionActivationController extends Controller
{
    public function create(RestaurantSubmission $submission, string $token): View|RedirectResponse
    {
        if ($this->wasActivated($submission, $token)) {
            return view('public.restaurant-submission.already-activated');
        }
        abort_unless($this->matches($submission, $token), 404);
        if (! $submission->activation_expires_at?->isFuture() && $this->eligible($submission)) {
            return view('public.expiring-link-status', ['eyebrow' => 'Activation de l’espace', 'title' => 'Ce lien a expiré.', 'message' => 'Vous pouvez recevoir un nouveau lien pour continuer.', 'resendUrl' => route('restaurant-submissions.activate.resend', [$submission, $token])]);
        }
        if (! $this->eligible($submission)) return view('public.expiring-link-status', ['eyebrow' => 'Activation de l’espace', 'title' => 'Cette demande a déjà été traitée.', 'message' => 'Cet espace ne peut plus être activé avec ce lien.', 'resendUrl' => null]);

        if ($submission->user?->login_enabled && ! $submission->user->must_change_password) {
            $submission->update(['activation_expires_at' => now()]);

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
            'login_enabled' => true,
            'must_change_password' => false,
            'status' => 'active',
            'email_verified_at' => now(),
        ])->save();
        $submission->update(['activation_expires_at' => now()]);
        Auth::login($submission->user);
        $request->session()->regenerate();

        return redirect()->route('account.dashboard')->with('status', 'Votre espace est activé. Vous pouvez gérer votre fiche.');
    }

    public function resend(RestaurantSubmission $submission, string $token, RestaurantSubmissionMailer $mailer): RedirectResponse
    {
        [$submission, $newToken] = DB::transaction(function () use ($submission, $token): array {
            $submission = RestaurantSubmission::query()->with('user')->lockForUpdate()->findOrFail($submission->id);
            abort_unless($this->matches($submission, $token) && ! $submission->activation_expires_at?->isFuture() && $this->eligible($submission), 404);
            $newToken = Str::random(64);
            $submission->update(['activation_token' => hash('sha256', $newToken), 'activation_expires_at' => now()->addDays(7)]);
            return [$submission->fresh('restaurant'), $newToken];
        });
        $mailer->confirmed($submission, route('restaurant-submissions.activate', [$submission, $newToken]));
        return back()->with('status', 'Un nouveau lien vient d’être envoyé.');
    }

    private function valid(RestaurantSubmission $submission, string $token): bool
    {
        return $this->eligible($submission)
            && $submission->activation_expires_at?->isFuture()
            && $this->matches($submission, $token);
    }

    private function matches(RestaurantSubmission $submission, string $token): bool { return filled($submission->activation_token) && hash_equals($submission->activation_token, hash('sha256', $token)); }
    private function eligible(RestaurantSubmission $submission): bool { return ! in_array($submission->status, ['pending_email_verification', 'rejected'], true) && $submission->user_id && $submission->user?->status === 'active' && ! ($submission->user?->login_enabled && ! $submission->user?->must_change_password); }

    private function wasActivated(RestaurantSubmission $submission, string $token): bool
    {
        return $submission->status !== 'rejected'
            && $this->matches($submission, $token)
            && $submission->user?->status === 'active'
            && $submission->user?->login_enabled
            && ! $submission->user->must_change_password
            && preg_match('/^[A-Za-z0-9]{64}$/D', $token) === 1;
    }
}
