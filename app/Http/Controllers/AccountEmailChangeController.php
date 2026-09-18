<?php

namespace App\Http\Controllers;

use App\Models\AccountEmailChange;
use App\Services\AccountEmailChangeService;
use App\Services\TransactionalMailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class AccountEmailChangeController extends Controller
{
    public function edit(): View
    {
        return view('account.email-change');
    }

    public function store(Request $request, AccountEmailChangeService $changes, TransactionalMailService $mail): RedirectResponse
    {
        $user = $request->user();
        $rules = ['email' => ['required', 'email:rfc', 'max:254']];

        // A future passwordless identity must not be forced to submit a random
        // placeholder hash. Every current connectable account has this field.
        if (filled($user->password)) {
            $rules['current_password'] = ['required', 'current_password:web'];
        }

        $data = $request->validate($rules);
        ['change' => $change, 'token' => $token] = $changes->initiate($user, $data['email']);
        $verificationUrl = URL::temporarySignedRoute('account.email-change.confirm', $change->expires_at, ['change' => $change, 'token' => $token]);

        $mail->queue('account_email_change_verification', $change->new_email, [
            'site_name' => config('app.name', 'Top Halal'),
            'user_name' => $user->name,
            'verification_url' => $verificationUrl,
        ]);

        return redirect()->route('account.dashboard')->with('status', 'Un lien de confirmation a été envoyé à votre nouvelle adresse. Votre adresse actuelle reste active jusque-là.');
    }

    public function confirm(Request $request, AccountEmailChange $change, string $token, AccountEmailChangeService $changes, TransactionalMailService $mail): View
    {
        if (! $request->hasValidSignature()) {
            return $this->unusableLink($change);
        }

        $result = $changes->confirm($change, $token);

        if ($result === null) {
            return $this->unusableLink($change->fresh());
        }

        $mail->queue('account_email_changed', $result['old_email'], [
            'site_name' => config('app.name', 'Top Halal'),
            'user_name' => $change->user?->name ?? '',
            'new_email' => $result['new_email'],
        ]);

        return view('public.expiring-link-status', [
            'eyebrow' => 'Adresse e-mail',
            'title' => 'Votre adresse e-mail est mise à jour.',
            'message' => 'Vous pouvez désormais vous connecter avec votre nouvelle adresse e-mail.',
            'resendUrl' => null,
            'exitUrl' => route('login'),
            'exitLabel' => 'Se connecter',
        ]);
    }

    private function unusableLink(?AccountEmailChange $change): View
    {
        $expired = $change?->expires_at && ! $change->expires_at->isFuture();

        return view('public.expiring-link-status', [
            'eyebrow' => 'Adresse e-mail',
            'title' => $expired ? 'Ce lien a expiré.' : 'Ce lien n’est plus utilisable.',
            'message' => 'Votre adresse e-mail actuelle n’a pas été modifiée.',
            'resendUrl' => null,
            'exitUrl' => route('account.dashboard'),
            'exitLabel' => 'Mon compte',
        ]);
    }
}
