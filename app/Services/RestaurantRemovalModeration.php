<?php
namespace App\Services;
use App\Models\RestaurantRemovalRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class RestaurantRemovalModeration { public function approve(RestaurantRemovalRequest $request): void { if($request->status!=='pending') throw ValidationException::withMessages(['status'=>'Cette demande a déjà été traitée.']); DB::transaction(function() use($request):void{$request->restaurant->update(['status'=>'archived']);$request->update(['status'=>'approved','reviewed_at'=>now(),'reviewed_by'=>auth()->id()]);app(AdminAudit::class)->record('restaurant_removal.approved',$request);}); } public function reject(RestaurantRemovalRequest $request): void { if($request->status!=='pending') throw ValidationException::withMessages(['status'=>'Cette demande a déjà été traitée.']);$request->update(['status'=>'rejected','reviewed_at'=>now(),'reviewed_by'=>auth()->id()]);app(AdminAudit::class)->record('restaurant_removal.rejected',$request); } }
