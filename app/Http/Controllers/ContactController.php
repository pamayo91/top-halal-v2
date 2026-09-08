<?php
namespace App\Http\Controllers;
use App\Http\Requests\StoreContactMessageRequest; use App\Models\{ContactMessage,Setting}; use App\Services\TransactionalMailService; use Illuminate\Http\{RedirectResponse,Request}; use Illuminate\View\View;
class ContactController extends Controller
{
    public function create(): View { $s=(array)(Setting::where('key','contact_settings')->value('value') ?? []); return view('public.contact.create',['settings'=>$s]); }
    public function store(StoreContactMessageRequest $request, TransactionalMailService $mail): RedirectResponse
    {
        if (filled($request->input('website'))) return back()->withInput()->withErrors(['message'=>'Votre envoi n’a pas pu être traité.']);
        $data=$request->validated(); $contact=ContactMessage::create(['name'=>trim($data['name']),'email'=>strtolower(trim($data['email'])),'subject'=>trim($data['subject']),'message'=>trim($data['message']),'ip_hash'=>hash_hmac('sha256',(string)$request->ip(),(string)config('app.key'))]);
        $s=(array)(Setting::where('key','contact_settings')->value('value') ?? []); $vars=['site_name'=>(Setting::where('key','site_name')->value('value')['text'] ?? 'Top Halal'),'contact_name'=>$contact->name,'contact_email'=>$contact->email,'contact_subject'=>$contact->subject,'contact_message'=>$contact->message];
        if (filled($s['recipient'] ?? null)) $mail->queue('contact_admin',$s['recipient'],$vars,$contact->email);
        if (($s['send_confirmation'] ?? false) === true) $mail->queue('contact_confirmation',$contact->email,$vars);
        return redirect()->route('contact.create')->with('status',$s['success_message'] ?? 'Votre message a bien été envoyé.');
    }
}
