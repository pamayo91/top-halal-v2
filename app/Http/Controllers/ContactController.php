<?php
namespace App\Http\Controllers;
use App\Http\Requests\StoreContactMessageRequest; use App\Models\{ContactMessage,Setting}; use App\Services\{ContactSpamProtection, TransactionalMailService}; use Illuminate\Database\UniqueConstraintViolationException; use Illuminate\Http\{RedirectResponse,Request}; use Illuminate\Support\Facades\DB; use Illuminate\View\View;
class ContactController extends Controller
{
    public function create(Request $request, ContactSpamProtection $spam): View
    {
        $reference = $request->query('reference');
        abort_unless($reference === null || preg_match('/^TH-PROP-[A-Z0-9]{12}$/', $reference) === 1, 404);

        return view('public.contact.create', [
            'prefillSubject' => $reference ? 'Contestation d’un refus de proposition' : null,
            'submissionReference' => $reference,
            'formToken' => $spam->issueFormToken(),
        ]);
    }
    public function store(StoreContactMessageRequest $request, TransactionalMailService $mail, ContactSpamProtection $spam): RedirectResponse
    {
        if (filled($request->input('website'))) {
            $spam->log('honeypot', $request);
            return $this->silentSuccess();
        }
        if ($spam->verifyFormToken($request->input('form_token')) !== 'ok') {
            $spam->log('time_trap', $request);
            return $this->silentSuccess();
        }
        $data=$request->validated(); $context = filled($data['submission_reference'] ?? null) ? "Référence proposition : {$data['submission_reference']}\n\n" : '';
        $fingerprint = $spam->fingerprint($data);
        $attributes = ['name'=>trim($data['name']),'email'=>strtolower(trim($data['email'])),'subject'=>trim($data['subject']),'message'=>$context.trim($data['message']),'ip_hash'=>$spam->hashIp($request),'fingerprint'=>$fingerprint];
        try { $contact = ContactMessage::create($attributes); }
        catch (UniqueConstraintViolationException) { $contact = $this->replaceExpiredFingerprint($fingerprint, $attributes); }
        if (! $contact) {
            $spam->log('duplicate', $request);
            return $this->silentSuccess();
        }
        $s=(array)(Setting::where('key','contact_settings')->value('value') ?? []); $vars=['site_name'=>(Setting::where('key','site_name')->value('value')['text'] ?? 'Top Halal'),'contact_name'=>$contact->name,'contact_email'=>$contact->email,'contact_subject'=>$contact->subject,'contact_message'=>$contact->message];
        if (filled($s['recipient'] ?? null)) $mail->queue('contact_admin',$s['recipient'],$vars,$contact->email);
        if (($s['send_confirmation'] ?? false) === true) $mail->queue('contact_confirmation',$contact->email,$vars);
        $spam->log('accepted', $request);
        return redirect()->route('contact.create')->with('status',$s['success_message'] ?? 'Votre message a bien été envoyé.');
    }

    private function silentSuccess(): RedirectResponse { return redirect()->route('contact.create')->with('status', 'Votre message a bien été envoyé.'); }

    /** Keeps the database uniqueness constraint as the concurrent duplicate backstop. */
    private function replaceExpiredFingerprint(string $fingerprint, array $attributes): ?ContactMessage
    {
        return DB::transaction(function () use ($fingerprint, $attributes): ?ContactMessage {
            $existing = ContactMessage::query()->where('fingerprint', $fingerprint)->lockForUpdate()->first();
            if (! $existing || $existing->created_at->gt(now()->subHour())) return null;
            $existing->update(['fingerprint' => null]);
            return ContactMessage::create($attributes);
        }, 3);
    }
}
