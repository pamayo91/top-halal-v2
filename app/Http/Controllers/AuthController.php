<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use App\Support\IntendedClaimRedirect;

class AuthController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt([...$credentials, 'status' => 'active'], $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'Les identifiants fournis sont invalides.']);
        }

        $request->session()->regenerate();

        if ($request->user()->must_change_password) {
            return redirect()->route('password.change');
        }

        // Never replay a stale legacy intended URL after authentication. In
        // particular, a URL that is not part of the protected panel must not escape to a
        // historical public redirect after an administrator signs in.
        $claimIntended = IntendedClaimRedirect::pull($request);

        if ($request->user()->role === 'admin') {
            return redirect()->to('/admin');
        }

        return $claimIntended ? redirect()->to($claimIntended) : redirect()->route('account.dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
