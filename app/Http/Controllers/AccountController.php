<?php

namespace App\Http\Controllers;

use App\Filament\Resources\UserResource;
use App\Models\Restaurant;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function dashboard(): View
    {
        $user = request()->user();
        $user->loadCount(['ownedRestaurants', 'submittedRestaurants', 'ownerSubmissions', 'legacyRestaurantAuthorships']);

        return view('account.dashboard', [
            'profileLabel' => UserResource::profileLabel($user),
            'restaurants' => Restaurant::query()
            ->with([
                'media.asset.variants',
                'claims' => fn ($claims) => $claims->where('user_id', $user->id)->where('status', 'approved'),
                'legacyAuthorships' => fn ($authorships) => $authorships->where('user_id', $user->id),
                'submission' => fn ($submission) => $submission->where('user_id', $user->id),
            ])
            ->withCount(['removalRequests as pending_removal_requests_count' => fn ($requests) => $requests->where('status', 'pending')])
            ->where(function ($query) use ($user): void {
                $query->whereHas('claims', fn ($claims) => $claims->where('user_id', $user->id)->where('status', 'approved'))
                    ->orWhereHas('legacyAuthorships', fn ($authorships) => $authorships->where('user_id', $user->id))
                    ->orWhere(function ($submissions) use ($user): void {
                        $submissions->whereHas('submission', fn ($submission) => $submission->where('user_id', $user->id)->where('status', '!=', 'rejected'))
                            ->whereDoesntHave('claims', fn ($claims) => $claims->where('status', 'approved'));
                    });
            })->orderBy('name')->get(),
        ]);
    }
}
