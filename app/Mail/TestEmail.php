<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class TestEmail extends Mailable
{
    public function build(): self { return $this->subject('Test e-mail - Top-Halal')->text('emails.test-text')->view('emails.test'); }
}
