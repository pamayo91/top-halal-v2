<?php

namespace App\Notifications;

use App\Models\RestaurantClaim;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Mail\TemplateMailable;
use Illuminate\Notifications\Notification;

class ClaimStatusNotification extends Notification implements ShouldQueue
{
    use Queueable, TransactionalMailQueueSettings;

    public function __construct(private readonly RestaurantClaim $claim, private readonly string $event) {}

    public function via(object $notifiable): array { return ['mail']; }

    public function toMail(object $notifiable): TemplateMailable
    {
        $restaurant = $this->claim->restaurant->name;
        $key=match($this->event){'submitted'=>'claim_received','approved'=>'claim_approved',default=>'claim_rejected'}; $url=$this->event==='submitted'?route('claims.show',$this->claim):($this->event==='approved'?route('owner.restaurants.edit',$this->claim->restaurant):null);
        return (new TemplateMailable($key,['site_name'=>config('app.name','Top Halal'),'user_name'=>$notifiable->name,'restaurant_name'=>$restaurant,'action_url'=>$url,'claim_note'=>$this->claim->admin_note ?: 'Vous pouvez contacter la modération avec les justificatifs nécessaires.']))->to($notifiable->email);
    }
}
