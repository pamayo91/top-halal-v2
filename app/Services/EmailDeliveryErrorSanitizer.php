<?php

namespace App\Services;

use Illuminate\Support\Str;
use Throwable;

class EmailDeliveryErrorSanitizer
{
    public static function message(Throwable $exception): string
    {
        return self::text($exception->getMessage());
    }

    public static function text(string $message): string
    {
        $message = trim(strip_tags($message));
        $message = preg_replace('/(?:smtp(?:\+ssl|\+tls)?:\/\/)[^@\s\/]+@/i', 'smtp://[masqué]@', $message) ?? $message;
        $message = preg_replace('/\b(password|passwd|secret|token|api[_-]?key)\b\s*[=:]\s*(?:"[^"]*"|\'[^\']*\'|[^\s,;]+)/i', '$1=[masqué]', $message) ?? $message;
        $message = preg_replace('/\bauthorization\s*:\s*[^\s,;]+/i', 'authorization: [masqué]', $message) ?? $message;
        $message = preg_replace('/\s+/', ' ', $message) ?? $message;

        return Str::limit($message ?: 'Erreur d’envoi inconnue.', 1000);
    }
}
