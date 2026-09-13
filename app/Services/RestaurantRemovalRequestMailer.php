<?php

namespace App\Services;

use App\Models\RestaurantRemovalRequest;
use App\Models\Setting;

class RestaurantRemovalRequestMailer
{
    public function received(RestaurantRemovalRequest $request): void
    {
        $this->queue('restaurant_removal_request_received', $request);
    }

    public function approved(RestaurantRemovalRequest $request): void
    {
        $this->queue('restaurant_removal_request_approved', $request);
    }

    public function rejected(RestaurantRemovalRequest $request): void
    {
        $this->queue('restaurant_removal_request_rejected', $request, [
            'removal_note' => $request->admin_note ?: 'Aucun motif complémentaire n’a été communiqué.',
        ]);
    }

    public function notifyTeam(RestaurantRemovalRequest $request): void
    {
        $recipient = Setting::query()->where('key', 'contact_settings')->first()?->value['recipient'] ?? null;

        if (blank($recipient)) {
            return;
        }

        $request->loadMissing('restaurant', 'user');

        app(TransactionalMailService::class)->queue('restaurant_removal_request_admin_review', $recipient, [
            'site_name' => config('app.name', 'Top Halal'),
            'restaurant_name' => $request->restaurant->name,
            'user_name' => $request->user->name,
            'removal_reason' => $this->reasonLabel($request->reason),
            'admin_url' => url('/admin/restaurant-removal-requests'),
        ], $request->user->email);
    }

    private function queue(string $template, RestaurantRemovalRequest $request, array $additionalValues = []): void
    {
        $request->loadMissing('restaurant', 'user');

        app(TransactionalMailService::class)->queue($template, $request->user->email, [
            'site_name' => config('app.name', 'Top Halal'),
            'user_name' => $request->user->name,
            'restaurant_name' => $request->restaurant->name,
        ] + $additionalValues);
    }

    private function reasonLabel(string $reason): string
    {
        return match ($reason) {
            'closed' => 'Restaurant définitivement fermé',
            'ownership_change' => 'Changement de propriétaire',
            'duplicate' => 'Doublon',
            'created_by_mistake' => 'Fiche créée par erreur',
            default => 'Autre',
        };
    }
}
