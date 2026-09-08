<?php
namespace App\Mail;
use App\Models\EmailDeliveryLog;
use App\Services\{EmailTemplateRenderer,MailSettings};
use Illuminate\Bus\Queueable; use Illuminate\Contracts\Queue\ShouldQueue; use Illuminate\Mail\Mailable; use Illuminate\Mail\Mailables\{Content,Envelope,Headers}; use Throwable;
class TemplateMailable extends Mailable implements ShouldQueue
{
    use Queueable;
    public function __construct(public string $templateKey, public array $values, public ?int $logId = null, public ?string $replyToAddress = null) {}
    public function envelope(): Envelope { $r=app(EmailTemplateRenderer::class)->render($this->templateKey,$this->values); return new Envelope(subject: $r['subject'] ?? ''); }
    public function content(): Content { $r=app(EmailTemplateRenderer::class)->render($this->templateKey,$this->values); return new Content(view:'emails.transactional', with:['email'=>$r]); }
    public function build(): static { $mailer=app(MailSettings::class)->apply(); $this->mailer($mailer); if ($this->replyToAddress) $this->replyTo($this->replyToAddress); if ($this->logId) EmailDeliveryLog::whereKey($this->logId)->update(['attempts'=>\DB::raw('attempts + 1'),'last_attempt_at'=>now()]); return $this; }
    public function headers(): Headers { return new Headers(text: $this->logId ? ['X-Top-Halal-Email-Log' => (string) $this->logId] : []); }
    public function failed(Throwable $exception): void { if ($this->logId) EmailDeliveryLog::whereKey($this->logId)->update(['status'=>'failed','error_message'=>str($exception->getMessage())->limit(1000)]); }
}
