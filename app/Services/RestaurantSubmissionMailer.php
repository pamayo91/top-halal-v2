<?php

namespace App\Services;

use App\Models\Restaurant;
use App\Models\RestaurantSubmission;
use App\Models\Setting;

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

    public function confirmed(RestaurantSubmission $submission, string $activationUrl): void
    {
        app(TransactionalMailService::class)->queue('restaurant_submission_email_confirmed', $submission->submitter_email, [
            'site_name' => config('app.name', 'Top Halal'),
            'restaurant_name' => $submission->restaurant->name,
            'activation_url' => $activationUrl,
        ]);
    }

    public function notifyTeamForReview(RestaurantSubmission $submission): void
    {
        $recipient = Setting::query()->where('key', 'contact_settings')->first()?->value['recipient'] ?? null;

        if (blank($recipient)) {
            return;
        }

        app(TransactionalMailService::class)->queue('restaurant_submission_admin_review', $recipient, [
            'site_name' => config('app.name', 'Top Halal'),
            'restaurant_name' => $submission->restaurant->name,
            'submitter_email' => $submission->submitter_email,
            'admin_url' => url('/admin/restaurants'),
        ], $submission->submitter_email);
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
