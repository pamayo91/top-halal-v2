<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LegacyAccountMigrationNotification extends Notification implements ShouldQueue
{
    use Queueable;
    public function via(object $notifiable): array { return ['mail']; }
    public function toMail(object $notifiable): MailMessage { return (new MailMessage)->subject('Votre compte Top-Halal évolue')->greeting('Bonjour '.$notifiable->name.',')->line('Un lien sécurisé de création de mot de passe vous sera communiqué lors de la migration.')->line('Aucun mot de passe temporaire n’est envoyé par e-mail.'); }
}
