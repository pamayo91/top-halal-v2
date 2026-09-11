<?php

namespace App\Notifications;

use App\Mail\TemplateMailable;
use App\Models\RestaurantClaim;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ClaimLifecycleNotification extends Notification implements ShouldQueue
{
    use Queueable, TransactionalMailQueueSettings;

    public function __construct(private readonly RestaurantClaim $claim, private readonly string $event, private readonly ?string $url = null) {}

    public function via(object $notifiable): array { return ['mail']; }

    public function toMail(object $notifiable): TemplateMailable
    {
        $key = match ($this->event) {
            'verify' => 'claim_email_verification', 'activate' => 'claim_activation',
            'approved' => 'claim_approved', 'rejected' => 'claim_rejected', default => 'claim_received',
        };
        return (new TemplateMailable($key, [
            'site_name' => config('app.name', 'Top Halal'),
            'user_name' => $this->claim->full_name ?: 'bonjour',
            'restaurant_name' => $this->claim->restaurant->name,
            'action_url' => $this->url,
            'verification_url' => $this->url,
            'activation_url' => $this->url,
            'claim_note' => $this->claim->admin_note ?: 'Vous pouvez contacter la modération si nécessaire.',
        ]))->to($notifiable->routeNotificationFor('mail'));
    }
}
