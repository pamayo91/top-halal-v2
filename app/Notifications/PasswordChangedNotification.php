<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    public function via(object $notifiable): array { return ['mail']; }
    public function toMail(object $notifiable): MailMessage { return (new MailMessage)->subject('Mot de passe modifié - Top-Halal')->greeting('Bonjour '.$notifiable->name.',')->line('Votre mot de passe Top-Halal vient d’être modifié.')->line('Si vous n’êtes pas à l’origine de cette action, contactez la modération.'); }
}
