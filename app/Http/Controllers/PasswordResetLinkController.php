<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);
        if (User::query()->where('email', strtolower((string) $request->input('email')))->where('login_enabled', true)->exists()) {
            Password::sendResetLink($request->only('email'));
        }

        return back()->with('status', 'Si ce compte existe, un lien de réinitialisation a été envoyé.');
    }
}
