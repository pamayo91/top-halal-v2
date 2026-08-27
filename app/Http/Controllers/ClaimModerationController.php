<?php

namespace App\Http\Controllers;

use App\Models\RestaurantClaim;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Notifications\ClaimStatusNotification;
use App\Services\AdminAudit;

class ClaimModerationController extends Controller
{
    public function index(Request $request): View
    {
        $this->ensureAdmin();

        return view('admin.claims.index', ['claims' => RestaurantClaim::query()
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->with(['restaurant', 'user'])
            ->orderBy('submitted_at')
            ->paginate(30)->withQueryString()]);
    }

    public function approve(Request $request, RestaurantClaim $claim, AdminAudit $audit): RedirectResponse
    {
        $this->ensureAdmin();
        abort_unless($claim->status === 'pending', 422);
        $claim->update(['status' => 'approved', 'reviewed_at' => now(), 'reviewed_by' => $request->user()->id]);
        if ($claim->user->role === 'user') {
            $claim->user->update(['role' => 'restaurant_owner']);
        }
        $claim->user->notify(new ClaimStatusNotification($claim, 'approved'));
        $audit->record('claim.approved', $claim);

        return redirect()->route('admin.claims.index')->with('status', 'Demande approuvée.');
    }

    public function reject(Request $request, RestaurantClaim $claim, AdminAudit $audit): RedirectResponse
    {
        $this->ensureAdmin();
        abort_unless($claim->status === 'pending', 422);
        $data = $request->validate(['admin_note' => ['nullable', 'string', 'max:1000']]);
        $claim->update(['status' => 'rejected', 'admin_note' => $data['admin_note'] ?? null, 'reviewed_at' => now(), 'reviewed_by' => $request->user()->id]);
        $claim->user->notify(new ClaimStatusNotification($claim, 'rejected'));
        $audit->record('claim.rejected', $claim, ['admin_note' => $data['admin_note'] ?? null]);

        return redirect()->route('admin.claims.index')->with('status', 'Demande refusée.');
    }

    private function ensureAdmin(): void
    {
        abort_unless(request()->user()?->role === 'admin', 403);
    }
}
