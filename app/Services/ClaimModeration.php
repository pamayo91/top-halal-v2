<?php
namespace App\Services;
use App\Models\RestaurantClaim;
use App\Notifications\ClaimLifecycleNotification;
use App\Models\User;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class ClaimModeration
{
    public function approve(RestaurantClaim $claim): void { if ($claim->status !== 'pending') throw ValidationException::withMessages(['status'=>'Cette demande a déjà été traitée.']); DB::transaction(function () use ($claim): void {$claim->loadMissing('user','restaurant'); $activationToken=null;$existing=null; if (! $claim->user) { $existing=User::query()->where('email',$claim->email)->first(); if (! $existing) { $newUser=User::create(['email'=>$claim->email,'name'=>$claim->full_name,'password'=>Str::random(64),'role'=>'restaurant_owner','status'=>'active','must_change_password'=>true]); $claim->user_id=$newUser->id; } $activationToken=Str::random(64); $claim->activation_token=hash('sha256',$activationToken); $claim->activation_expires_at=now()->addDays(7); } $claim->status='approved';$claim->reviewed_at=now();$claim->reviewed_by=auth()->id();$claim->save();$claim->load('user'); if($claim->user?->role==='user')$claim->user->update(['role'=>'restaurant_owner']); if ($activationToken) { $url=URL::temporarySignedRoute('claims.activate', now()->addDays(7), ['claim'=>$claim,'token'=>$activationToken]); if($existing) $existing->notify(new ClaimLifecycleNotification($claim,'activate',$url)); else $claim->user->notify(new ClaimLifecycleNotification($claim,'activate',$url)); } else $claim->user->notify(new ClaimLifecycleNotification($claim,'approved',route('owner.restaurants.edit',$claim->restaurant))); app(AdminAudit::class)->record('claim.approved',$claim);}); }
    public function reject(RestaurantClaim $claim, ?string $note): void { if ($claim->status !== 'pending') throw ValidationException::withMessages(['status'=>'Cette demande a déjà été traitée.']); DB::transaction(function () use ($claim,$note): void {$claim->loadMissing('user');$claim->update(['status'=>'rejected','admin_note'=>$note,'reviewed_at'=>now(),'reviewed_by'=>auth()->id()]);if($claim->user)$claim->user->notify(new ClaimLifecycleNotification($claim,'rejected'));elseif($claim->email)\Illuminate\Support\Facades\Notification::route('mail',$claim->email)->notify(new ClaimLifecycleNotification($claim,'rejected'));app(AdminAudit::class)->record('claim.rejected',$claim,['admin_note'=>$note]);}); }
}
