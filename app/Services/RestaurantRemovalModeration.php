<?php

namespace App\Services;

use App\Models\RestaurantRemovalRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RestaurantRemovalModeration
{
    public function approve(RestaurantRemovalRequest $request): void
    {
        $requestId = DB::transaction(function () use ($request): int {
            $locked = RestaurantRemovalRequest::query()->lockForUpdate()->findOrFail($request->id);
            $this->requirePending($locked);

            $locked->loadMissing('restaurant');
            $locked->restaurant->update(['status' => 'archived']);
            $locked->update([
                'status' => 'approved',
                'reviewed_at' => now(),
                'reviewed_by' => auth()->id(),
            ]);
            app(AdminAudit::class)->record('restaurant_removal.approved', $locked);

            return $locked->id;
        });

        app(RestaurantRemovalRequestMailer::class)->approved(RestaurantRemovalRequest::findOrFail($requestId));
    }

    public function reject(RestaurantRemovalRequest $request, ?string $note = null): void
    {
        $requestId = DB::transaction(function () use ($request, $note): int {
            $locked = RestaurantRemovalRequest::query()->lockForUpdate()->findOrFail($request->id);
            $this->requirePending($locked);

            $locked->update([
                'status' => 'rejected',
                'admin_note' => $note,
                'reviewed_at' => now(),
                'reviewed_by' => auth()->id(),
            ]);
            app(AdminAudit::class)->record('restaurant_removal.rejected', $locked, ['admin_note' => $note]);

            return $locked->id;
        });

        app(RestaurantRemovalRequestMailer::class)->rejected(RestaurantRemovalRequest::findOrFail($requestId));
    }

    private function requirePending(RestaurantRemovalRequest $request): void
    {
        if ($request->status !== 'pending') {
            throw ValidationException::withMessages(['status' => 'Cette demande a déjà été traitée.']);
        }
    }
}
