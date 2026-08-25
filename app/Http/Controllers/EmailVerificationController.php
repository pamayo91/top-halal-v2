<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationController extends Controller
{
    public function notice(): View { return view('auth.verify-email'); }
    public function verify(EmailVerificationRequest $request): RedirectResponse { $request->fulfill(); return redirect()->route('account.dashboard')->with('status', 'Adresse e-mail confirmée.'); }
    public function send(Request $request): RedirectResponse { if (! $request->user()->hasVerifiedEmail()) $request->user()->sendEmailVerificationNotification(); return back()->with('status', 'Lien de vérification mis en file.'); }
}
