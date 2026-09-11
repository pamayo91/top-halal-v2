<?php
namespace App\Http\Controllers;
use App\Models\{Restaurant,RestaurantRemovalRequest};
use Illuminate\Http\{RedirectResponse,Request};
use Illuminate\View\View;
class RestaurantRemovalRequestController extends Controller {
 public function create(Restaurant $restaurant): View { $this->authorize('manage',$restaurant); return view('account.restaurant-removal-request',compact('restaurant')); }
 public function store(Request $request, Restaurant $restaurant): RedirectResponse { $this->authorize('manage',$restaurant); abort_if($restaurant->removalRequests()->where('status','pending')->exists(),409,'Une demande de suppression est déjà en cours.'); $data=$request->validate(['reason'=>['required','in:closed,ownership_change,duplicate,created_by_mistake,other'],'comment'=>['nullable','string','max:2000','required_if:reason,other']]); RestaurantRemovalRequest::create(['restaurant_id'=>$restaurant->id,'user_id'=>$request->user()->id,'reason'=>$data['reason'],'comment'=>$data['comment']??null,'status'=>'pending','submitted_at'=>now()]); return redirect()->route('account.dashboard')->with('status','Votre demande de suppression a bien été envoyée.'); }
}
