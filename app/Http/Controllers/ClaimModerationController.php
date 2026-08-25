<?php

namespace App\Http\Controllers;

use App\Models\RestaurantClaim;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClaimModerationController extends Controller
{
    public function index(): View
    {
        $this->ensureAdmin();

        return view('admin.claims.index', ['claims' => RestaurantClaim::query()
            ->where('status', 'pending')
            ->with(['restaurant', 'user'])
            ->orderBy('submitted_at')
            ->get()]);
    }

    public function approve(Request $request, RestaurantClaim $claim): RedirectResponse
    {
        $this->ensureAdmin();
        abort_unless($claim->status === 'pending', 422);
        $claim->update(['status' => 'approved', 'reviewed_at' => now(), 'reviewed_by' => $request->user()->id]);
        if ($claim->user->role === 'user') {
            $claim->user->update(['role' => 'restaurant_owner']);
        }

        return redirect()->route('admin.claims.index')->with('status', 'Demande approuvée.');
    }

    public function reject(Request $request, RestaurantClaim $claim): RedirectResponse
    {
        $this->ensureAdmin();
        abort_unless($claim->status === 'pending', 422);
        $data = $request->validate(['admin_note' => ['nullable', 'string', 'max:1000']]);
        $claim->update(['status' => 'rejected', 'admin_note' => $data['admin_note'] ?? null, 'reviewed_at' => now(), 'reviewed_by' => $request->user()->id]);

        return redirect()->route('admin.claims.index')->with('status', 'Demande refusée.');
    }

    private function ensureAdmin(): void
    {
        abort_unless(request()->user()?->role === 'admin', 403);
    }
}
