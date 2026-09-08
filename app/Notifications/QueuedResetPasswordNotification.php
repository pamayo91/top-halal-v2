<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Mail\TemplateMailable;

class QueuedResetPasswordNotification extends ResetPassword implements ShouldQueue
{
    use Queueable;
    public function toMail($notifiable): TemplateMailable { return (new TemplateMailable('password_reset',['site_name'=>config('app.name','Top Halal'),'user_name'=>$notifiable->name,'reset_url'=>$this->resetUrl($notifiable)]))->to($notifiable->routeNotificationForMail()); }
}
