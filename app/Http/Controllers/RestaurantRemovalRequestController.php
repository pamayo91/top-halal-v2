<?php

namespace App\Http\Controllers;

use App\Models\{Restaurant, RestaurantRemovalRequest};
use App\Services\RestaurantRemovalRequestMailer;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RestaurantRemovalRequestController extends Controller
{
    public function create(Restaurant $restaurant): View|RedirectResponse
    {
        if (! request()->user()->can('manage', $restaurant)) {
            return redirect()->route('owner.restaurants.management-unavailable', $restaurant);
        }

        return view('account.restaurant-removal-request', compact('restaurant'));
    }

    public function store(Request $request, Restaurant $restaurant, RestaurantRemovalRequestMailer $mailer): RedirectResponse
    {
        if (! $request->user()->can('manage', $restaurant)) {
            return redirect()->route('owner.restaurants.management-unavailable', $restaurant);
        }

        $data = $request->validate([
            'reason' => ['required', 'in:closed,ownership_change,duplicate,created_by_mistake,other'],
            'comment' => ['nullable', 'string', 'max:2000', 'required_if:reason,other'],
        ]);

        $removalRequest = DB::transaction(function () use ($request, $restaurant, $data): RestaurantRemovalRequest {
            $locked = Restaurant::query()->lockForUpdate()->findOrFail($restaurant->id);

            if (! $request->user()->can('manage', $locked)) {
                abort(403);
            }

            abort_if(
                $locked->removalRequests()->where('status', 'pending')->exists(),
                409,
                'Une demande de suppression est déjà en cours.',
            );

            return RestaurantRemovalRequest::create([
                'restaurant_id' => $locked->id,
                'user_id' => $request->user()->id,
                'reason' => $data['reason'],
                'comment' => $data['comment'] ?? null,
                'status' => 'pending',
                'submitted_at' => now(),
            ]);
        });

        $mailer->received($removalRequest);
        $mailer->notifyTeam($removalRequest);

        return redirect()->route('account.dashboard')->with('status', 'Votre demande de suppression a bien été envoyée.');
    }
}
