<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Mail\TemplateMailable;
use Illuminate\Notifications\Notification;

class PasswordChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    public function via(object $notifiable): array { return ['mail']; }
    public function toMail(object $notifiable): TemplateMailable { return new TemplateMailable('password_changed',['site_name'=>config('app.name','Top Halal'),'user_name'=>$notifiable->name]); }
}
