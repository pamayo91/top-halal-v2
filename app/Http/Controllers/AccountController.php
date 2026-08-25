<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class AccountController extends Controller
{
    public function dashboard(): View
    {
        return view('account.dashboard', ['restaurants' => request()->user()->ownedRestaurants()->get()]);
    }
}
