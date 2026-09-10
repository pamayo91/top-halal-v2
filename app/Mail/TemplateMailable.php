<?php
namespace App\Mail;
use App\Models\EmailDeliveryLog;
use App\Exceptions\EmailDeliveryUnavailableException;
use App\Services\{EmailTemplateRenderer,MailSettings};
use Illuminate\Bus\Queueable; use Illuminate\Contracts\Queue\ShouldQueue; use Illuminate\Mail\Mailable; use Illuminate\Mail\Mailables\{Content,Envelope,Headers}; use Illuminate\Support\Facades\DB; use Throwable;
class TemplateMailable extends Mailable implements ShouldQueue
{
    use Queueable;
    public int $tries = 4;
    public int $timeout = 75;
    public array $backoff = [30, 120, 300];
    public function __construct(public string $templateKey, public array $values, public ?int $logId = null, public ?string $replyToAddress = null) {}
    public function envelope(): Envelope { $r=app(EmailTemplateRenderer::class)->render($this->templateKey,$this->values); return new Envelope(subject: $r['subject'] ?? ''); }
    public function content(): Content { $r=app(EmailTemplateRenderer::class)->render($this->templateKey,$this->values); return new Content(view:'emails.transactional', with:['email'=>$r]); }
    public function build(): static {
        $mailer=app(MailSettings::class)->apply();
        $this->mailer($mailer);
        if ($this->replyToAddress) $this->replyTo($this->replyToAddress);

        if ($this->logId) {
            $claimed = EmailDeliveryLog::query()->whereKey($this->logId)
                ->where(function ($query): void {
                    $query->where('status', EmailDeliveryLog::STATUS_QUEUED)
                        ->orWhere(function ($query): void {
                            $query->where('status', EmailDeliveryLog::STATUS_PROCESSING)
                                ->where('last_attempt_at', '<=', now()->subSeconds((int) config('queue.connections.database.retry_after', 90) - 5));
                        });
                })
                ->update([
                    'status' => EmailDeliveryLog::STATUS_PROCESSING,
                    'attempts' => DB::raw('attempts + 1'),
                    'last_attempt_at' => now(),
                ]);

            if ($claimed !== 1) throw new EmailDeliveryUnavailableException('Cet e-mail ne peut plus être envoyé depuis ce job.');
        }

        return $this;
    }
    public function headers(): Headers { return new Headers(text: $this->logId ? ['X-Top-Halal-Email-Log' => (string) $this->logId] : []); }
    public function failed(Throwable $exception): void { if ($this->logId) EmailDeliveryLog::whereKey($this->logId)->whereNotIn('status',[EmailDeliveryLog::STATUS_SENT,EmailDeliveryLog::STATUS_CANCELLED,EmailDeliveryLog::STATUS_EXPIRED])->update(['status'=>EmailDeliveryLog::STATUS_FAILED,'error_message'=>app(\App\Services\EmailDeliveryErrorSanitizer::class)::message($exception)]); }
}
