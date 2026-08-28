<?php
namespace App\Services;
use App\Models\RestaurantClaim;
use App\Notifications\ClaimStatusNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class ClaimModeration
{
    public function approve(RestaurantClaim $claim): void { if ($claim->status !== 'pending') throw ValidationException::withMessages(['status'=>'Cette demande a déjà été traitée.']); DB::transaction(function () use ($claim): void {$claim->loadMissing('user');$claim->update(['status'=>'approved','reviewed_at'=>now(),'reviewed_by'=>auth()->id()]);if($claim->user->role==='user')$claim->user->update(['role'=>'restaurant_owner']);$claim->user->notify(new ClaimStatusNotification($claim,'approved'));app(AdminAudit::class)->record('claim.approved',$claim);}); }
    public function reject(RestaurantClaim $claim, ?string $note): void { if ($claim->status !== 'pending') throw ValidationException::withMessages(['status'=>'Cette demande a déjà été traitée.']); DB::transaction(function () use ($claim,$note): void {$claim->loadMissing('user');$claim->update(['status'=>'rejected','admin_note'=>$note,'reviewed_at'=>now(),'reviewed_by'=>auth()->id()]);$claim->user->notify(new ClaimStatusNotification($claim,'rejected'));app(AdminAudit::class)->record('claim.rejected',$claim,['admin_note'=>$note]);}); }
}
