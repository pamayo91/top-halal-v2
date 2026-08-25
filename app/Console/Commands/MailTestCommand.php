<?php

namespace App\Console\Commands;

use App\Mail\TestEmail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class MailTestCommand extends Command
{
    protected $signature = 'mail:test {address : Recipient address}';
    public function handle(): int
    {
        if (! filter_var($this->argument('address'), FILTER_VALIDATE_EMAIL)) { $this->error('Adresse e-mail invalide.'); return self::FAILURE; }
        try { Mail::to($this->argument('address'))->queue(new TestEmail()); $this->info('E-mail de test mis en file.'); return self::SUCCESS; }
        catch (Throwable $exception) { report($exception); $this->error('Mise en file impossible. Consultez les journaux techniques.'); return self::FAILURE; }
    }
}
