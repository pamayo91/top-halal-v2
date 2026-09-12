<?php

namespace App\Services;

use App\Models\Restaurant;
use App\Models\RestaurantSubmission;

class RestaurantSubmissionMailer
{
    public function received(RestaurantSubmission $submission): void
    {
        app(TransactionalMailService::class)->queue('restaurant_submission_received', $submission->submitter_email, [
            'site_name' => config('app.name', 'Top Halal'),
            'restaurant_name' => $submission->restaurant->name,
        ]);
    }

    public function published(Restaurant $restaurant): void
    {
        $submission = $restaurant->submission;

        if (! $submission) {
            return;
        }

        app(TransactionalMailService::class)->queue('restaurant_published', $submission->submitter_email, [
            'site_name' => config('app.name', 'Top Halal'),
            'restaurant_name' => $restaurant->name,
            'restaurant_url' => route('restaurants.show', $restaurant->slug),
        ]);
    }
}
