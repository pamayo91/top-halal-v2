<?php

namespace App\Http\Controllers;

use App\Models\Restaurant;
use App\Models\RestaurantClaim;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Notifications\ClaimStatusNotification;

class RestaurantClaimController extends Controller
{
    public function create(Restaurant $restaurant): View
    {
        abort_unless($restaurant->isClaimable(), 409, 'Ce restaurant est déjà géré ou fait actuellement l’objet d’une demande de revendication.');
        return view('claims.create', ['restaurant' => $restaurant, 'claim' => $restaurant->claims()->where('user_id', request()->user()->id)->first()]);
    }

    public function store(Request $request, Restaurant $restaurant): RedirectResponse
    {
        $request->merge(['siret'=>preg_replace('/\D+/', '', (string) $request->input('siret'))]);
        if (! $restaurant->isClaimable()) return back()->withErrors(['claim'=>'Ce restaurant est déjà géré ou fait actuellement l’objet d’une demande de revendication.']);
        $data = $request->validate(['full_name'=>['required','string','max:255'],'company'=>['required','string','max:255'],'siret'=>['required','digits:14'],'certified'=>['accepted'],'identity_document'=>['required','file','mimetypes:image/jpeg,image/png,image/webp','max:10240'],'message' => ['nullable', 'string', 'max:1000']]);
        $siret=preg_replace('/\D+/', '', $data['siret']);
        if (! $this->isValidSiret($siret)) return back()->withErrors(['siret'=>'Le SIRET doit comporter 14 chiffres valides.'])->withInput();
        $path = $request->file('identity_document')->store('claims/identity-documents', 'local');
        $claim = RestaurantClaim::updateOrCreate(['restaurant_id'=>$restaurant->id,'user_id'=>$request->user()->id],['status'=>'pending','full_name'=>$data['full_name'],'company'=>$data['company'],'siret'=>$siret,'certified'=>true,'identity_document_path'=>$path,'source'=>'claim','message'=>$data['message']??null,'submitted_at'=>now(),'reviewed_at'=>null,'reviewed_by'=>null,'admin_note'=>null]);
        $request->user()->notify(new ClaimStatusNotification($claim, 'submitted'));

        return redirect()->route('claims.show', $claim)->with('status', 'Votre demande de revendication a bien été envoyée. Elle sera vérifiée par l’équipe Top Halal.');
    }

    public function show(RestaurantClaim $claim): View
    {
        abort_unless($claim->user_id === request()->user()->id || request()->user()->role === 'admin', 403);

        return view('claims.show', compact('claim'));
    }
    private function isValidSiret(string $siret): bool { if (!preg_match('/^\d{14}$/',$siret)) return false; $sum=0; foreach(str_split($siret) as $i=>$digit){$n=(int)$digit;if($i%2===0){$n*=2;if($n>9)$n-=9;}$sum+=$n;}return $sum%10===0; }
}
