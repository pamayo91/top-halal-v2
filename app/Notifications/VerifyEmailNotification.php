<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Mail\TemplateMailable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Config;

class VerifyEmailNotification extends Notification implements ShouldQueue
{
    use Queueable, TransactionalMailQueueSettings;

    public function via(object $notifiable): array { return ['mail']; }

    public function toMail(object $notifiable): TemplateMailable
    {
        return (new TemplateMailable('email_verification', [
            'site_name' => config('app.name', 'Top Halal'),
            'user_name' => $notifiable->name,
            'verification_url' => URL::temporarySignedRoute('verification.verify', now()->addMinutes(Config::get('auth.verification.expire', 60)), ['id' => $notifiable->getKey(), 'hash' => sha1($notifiable->getEmailForVerification())]),
        ]))->to($notifiable->email);
    }
}
