<?php

namespace App\Http\Controllers;

use App\Models\ContributionVerification;
use App\Services\ContributionIdentityService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ContributionVerificationController extends Controller
{
    public function __invoke(Request $request, ContributionVerification $verification, string $token, ContributionIdentityService $identities): View
    {
        $state = $identities->linkState($verification, $token);
        if ($state === 'invalid') abort(404);
        if ($state !== 'valid') return view('public.expiring-link-status', ['eyebrow' => 'Vérification e-mail', 'title' => $state === 'expired' ? 'Ce lien a expiré.' : 'Cette contribution a déjà été confirmée.', 'message' => $state === 'expired' ? 'Vous pouvez recevoir un nouveau lien pour continuer.' : 'Aucune nouvelle contribution ne sera créée.', 'resendUrl' => $state === 'expired' ? route('contributions.verify.resend', [$verification, $token]) : null]);
        $request->session()->regenerate();
        $result = $identities->verify($request, $verification, $token);

        return view('public.contributions.email-verified', $result);
    }

    public function resend(ContributionVerification $verification, string $token, ContributionIdentityService $identities): RedirectResponse
    {
        $identities->resend($verification, $token);
        return back()->with('status', 'Un nouveau lien vient d’être envoyé.');
    }
}
