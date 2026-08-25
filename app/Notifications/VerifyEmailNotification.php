<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Config;

class VerifyEmailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array { return ['mail']; }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Confirmez votre adresse e-mail - Top-Halal')
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line('Confirmez votre adresse e-mail pour activer votre compte Top-Halal.')
            ->action('Confirmer mon adresse e-mail', URL::temporarySignedRoute('verification.verify', now()->addMinutes(Config::get('auth.verification.expire', 60)), ['id' => $notifiable->getKey(), 'hash' => sha1($notifiable->getEmailForVerification())]))
            ->line('Ce lien est temporaire et ne doit pas être transféré.');
    }
}
