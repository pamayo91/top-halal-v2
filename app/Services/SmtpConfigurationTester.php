<?php

namespace App\Services;

use App\Mail\TestEmail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class SmtpConfigurationTester
{
    public function send(string $recipient): void
    {
        try {
            $settings = app(MailSettings::class);
            $values = $settings->values();

            if (($values['mailer'] ?? config('mail.default')) !== 'smtp') {
                throw new \RuntimeException('Sélectionnez le transport SMTP avant de lancer le test.');
            }

            if (blank($values['host'] ?? null)) {
                throw new \RuntimeException('Renseignez l’hôte SMTP avant de lancer le test.');
            }

            $mailer = $settings->apply();

            if ($mailer !== 'smtp') {
                throw new \RuntimeException('La configuration SMTP n’a pas pu être appliquée.');
            }

            Mail::mailer($mailer)->to($recipient)->send(new TestEmail());
        } catch (Throwable $exception) {
            $message = $this->safeMessage($exception);

            Log::error('SMTP configuration test failed.', ['error' => $message]);

            throw new SmtpConfigurationTestException($message, previous: $exception);
        }
    }

    private function safeMessage(Throwable $exception): string
    {
        $message = trim(strip_tags($exception->getMessage()));
        $message = preg_replace('/(?:smtp(?:\+ssl|\+tls)?:\/\/)[^@\s\/]+@/i', 'smtp://[masqué]@', $message) ?? $message;
        $message = preg_replace('/\b(password|passwd|secret|token|api[_-]?key)\b\s*[=:]\s*(?:"[^"]*"|\'[^\']*\'|[^\s,;]+)/i', '$1=[masqué]', $message) ?? $message;
        $message = preg_replace('/\bauthorization\s*:\s*[^\s,;]+/i', 'authorization: [masqué]', $message) ?? $message;
        $message = preg_replace('/\s+/', ' ', $message) ?? $message;

        return Str::limit($message ?: 'Erreur SMTP inconnue.', 500);
    }
}
