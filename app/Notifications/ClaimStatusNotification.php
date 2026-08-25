<?php

namespace App\Notifications;

use App\Models\RestaurantClaim;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClaimStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly RestaurantClaim $claim, private readonly string $event) {}

    public function via(object $notifiable): array { return ['mail']; }

    public function toMail(object $notifiable): MailMessage
    {
        $restaurant = $this->claim->restaurant->name;
        return match ($this->event) {
            'submitted' => (new MailMessage)->subject('Demande de revendication reçue - Top-Halal')->greeting('Bonjour '.$notifiable->name.',')->line('Votre demande pour '.$restaurant.' est en attente de modération.')->action('Voir ma demande', route('claims.show', $this->claim)),
            'approved' => (new MailMessage)->subject('Revendication acceptée - Top-Halal')->greeting('Bonjour '.$notifiable->name.',')->line('Votre revendication de '.$restaurant.' a été acceptée.')->action('Gérer mon restaurant', route('owner.restaurants.edit', $this->claim->restaurant)),
            default => (new MailMessage)->subject('Revendication refusée - Top-Halal')->greeting('Bonjour '.$notifiable->name.',')->line('Votre revendication de '.$restaurant.' a été refusée.')->line($this->claim->admin_note ? 'Note : '.$this->claim->admin_note : 'Vous pouvez contacter la modération avec les justificatifs nécessaires.'),
        };
    }
}
