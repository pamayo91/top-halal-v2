<?php

namespace App\Mail;

use App\Services\EmailGlobalSettings;
use App\Services\EmailTemplateRenderer;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;

class TestEmail extends Mailable
{
    public function __construct() { $this->subject('Test e-mail - Top-Halal'); }
    public function content(): Content { $body = 'Ce message confirme que la configuration e-mail fonctionne bien.'; return new Content(view: 'emails.transactional', text: 'emails.transactional-text', with: ['email' => ['body' => $body, 'body_paragraphs' => app(EmailTemplateRenderer::class)->bodyParagraphs($body), 'cta_label' => null, 'cta_url' => null], 'global' => app(EmailGlobalSettings::class)->forRender()]); }
}
