<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Notifications\PasswordChangedNotification;
use Illuminate\View\View;

class PasswordChangeController extends Controller
{
    public function edit(): View
    {
        return view('auth.change-password');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password:web'],
            'password' => ['required', 'confirmed', 'min:12'],
        ]);

        $request->user()->forceFill([
            'password' => Hash::make($data['password']),
            'must_change_password' => false,
        ])->save();
        $request->user()->notify(new PasswordChangedNotification());
        $request->session()->regenerate();

        return redirect()->to($request->user()->role === 'admin' ? '/admin' : route('account.dashboard'))
            ->with('status', 'Mot de passe mis à jour.');
    }
}
