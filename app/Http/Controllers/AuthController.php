<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

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
        // particular, old cached `/admin` requests must not escape to a
        // historical public redirect after an administrator signs in.
        $intended = (string) $request->session()->pull('url.intended', '');
        $path = (string) (parse_url($intended, PHP_URL_PATH) ?: '');
        $host = (string) (parse_url($intended, PHP_URL_HOST) ?: '');
        $isLocalIntended = $host === '' || hash_equals($request->getHost(), $host);

        if ($request->user()->role === 'admin') {
            return $isLocalIntended && str_starts_with($path, '/bo')
                ? redirect()->to($intended)
                : redirect()->route('admin.dashboard');
        }

        return redirect()->route('account.dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
