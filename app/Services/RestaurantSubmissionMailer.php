<?php

namespace App\Services;

use App\Models\Restaurant;
use App\Models\RestaurantSubmission;

class RestaurantSubmissionMailer
{
    public function verification(RestaurantSubmission $submission, string $verificationUrl): void
    {
        app(TransactionalMailService::class)->queue('restaurant_submission_email_verification', $submission->submitter_email, [
            'site_name' => config('app.name', 'Top Halal'),
            'restaurant_name' => $submission->restaurant->name,
            'verification_url' => $verificationUrl,
        ]);
    }

    public function confirmed(RestaurantSubmission $submission): void
    {
        app(TransactionalMailService::class)->queue('restaurant_submission_email_confirmed', $submission->submitter_email, [
            'site_name' => config('app.name', 'Top Halal'),
            'restaurant_name' => $submission->restaurant->name,
        ]);
    }

    public function published(Restaurant $restaurant): void
    {
        $submission = $restaurant->submission;

        if (! $submission || $submission->status !== 'pending_admin_review') {
            return;
        }

        $submission->update(['status' => 'published']);

        app(TransactionalMailService::class)->queue('restaurant_published', $submission->submitter_email, [
            'site_name' => config('app.name', 'Top Halal'),
            'restaurant_name' => $restaurant->name,
            'restaurant_url' => route('restaurants.show', $restaurant->slug),
        ]);
    }
}
