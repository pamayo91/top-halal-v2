<?php

namespace App\Mail;

use App\Services\EmailGlobalSettings;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;

class TestEmail extends Mailable
{
    public function __construct() { $this->subject('Test e-mail - Top-Halal'); }
    public function content(): Content { return new Content(view: 'emails.transactional', text: 'emails.transactional-text', with: ['email' => ['body' => 'Ce message confirme que la configuration e-mail fonctionne bien.', 'cta_label' => null, 'cta_url' => null], 'global' => app(EmailGlobalSettings::class)->forRender()]); }
}
