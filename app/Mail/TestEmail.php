<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;

class TestEmail extends Mailable implements ShouldQueue
{
    use Queueable;
    public function build(): self { return $this->subject('Test e-mail - Top-Halal')->text('emails.test-text')->view('emails.test'); }
}
