<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmailNotification extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Confirmez votre adresse e-mail - Top-Halal')
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line('Confirmez votre adresse e-mail pour activer votre compte Top-Halal.')
            ->action('Confirmer mon adresse e-mail', $this->verificationUrl($notifiable))
            ->line('Ce lien est temporaire et ne doit pas être transféré.');
    }
}
