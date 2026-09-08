<?php
namespace App\Services;
use App\Mail\TemplateMailable; use App\Models\EmailDeliveryLog; use Illuminate\Support\Facades\Mail;
class TransactionalMailService
{
    public function queue(string $templateKey, string $recipient, array $values = [], ?string $replyTo = null): ?EmailDeliveryLog
    {
        $rendered=app(EmailTemplateRenderer::class)->render($templateKey,$values); if (!($rendered['active'] ?? false)) return null;
        $log=EmailDeliveryLog::create(['template_key'=>$templateKey,'recipient'=>$recipient,'status'=>'queued']);
        Mail::to($recipient)->queue(new TemplateMailable($templateKey,$values,$log->id,$replyTo)); return $log;
    }
}
