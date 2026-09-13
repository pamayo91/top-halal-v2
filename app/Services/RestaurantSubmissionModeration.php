<?php

namespace App\Services;

use App\Models\Restaurant;
use App\Models\RestaurantSubmission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RestaurantSubmissionModeration
{
    public function reject(Restaurant $restaurant, ?string $reason = null): void
    {
        $submissionId = DB::transaction(function () use ($restaurant, $reason): int {
            $lockedRestaurant = Restaurant::query()->lockForUpdate()->findOrFail($restaurant->id);
            $submission = RestaurantSubmission::query()
                ->with('restaurant')
                ->lockForUpdate()
                ->where('restaurant_id', $lockedRestaurant->id)
                ->firstOrFail();

            if ($lockedRestaurant->status !== 'pending' || $submission->status !== 'pending_admin_review') {
                throw ValidationException::withMessages(['status' => 'Cette proposition ne peut plus être refusée.']);
            }

            $reason = filled($reason) ? trim((string) $reason) : null;
            $submission->update([
                'status' => 'rejected',
                'admin_rejection_reason' => $reason,
                'rejection_reference' => 'TH-PROP-'.Str::upper(Str::random(12)),
                'rejected_at' => now(),
                'rejected_by' => auth()->id(),
                // Keep only the hash so the holder of this exact old link receives
                // a clear final state; the rejected status makes it unusable.
                'activation_expires_at' => now(),
            ]);

            app(AdminAudit::class)->record('restaurant_submission.rejected', $submission, [
                'restaurant_id' => $lockedRestaurant->id,
                'admin_rejection_reason' => $reason,
                'rejection_reference' => $submission->rejection_reference,
            ]);

            return $submission->id;
        });

        app(RestaurantSubmissionMailer::class)->rejected(RestaurantSubmission::findOrFail($submissionId));
    }
}
