<?php

namespace App\Http\Controllers;

use App\Models\Restaurant;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function dashboard(): View
    {
        $user = request()->user();
        return view('account.dashboard', ['restaurants' => Restaurant::query()
            ->where(function ($query) use ($user): void {
                $query->whereHas('claims', fn ($claims) => $claims->where('user_id', $user->id)->where('status', 'approved'))
                    ->orWhereHas('legacyAuthorships', fn ($authorships) => $authorships->where('user_id', $user->id))
                    ->orWhere(function ($submissions) use ($user): void {
                        $submissions->whereHas('submission', fn ($submission) => $submission->where('user_id', $user->id)->where('status', '!=', 'rejected'))
                            ->whereDoesntHave('claims', fn ($claims) => $claims->where('status', 'approved'));
                    });
            })->orderBy('name')->get()]);
    }
}
