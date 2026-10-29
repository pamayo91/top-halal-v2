<?php

namespace App\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class ContactSpamProtection
{
    private const TOKEN_TTL_SECONDS = 86400;
    private const MINIMUM_FILL_SECONDS = 2;

    public function issueFormToken(): string
    {
        return Crypt::encryptString(json_encode([
            'issued_at' => now()->getTimestamp(),
            'nonce' => bin2hex(random_bytes(16)),
        ], JSON_THROW_ON_ERROR));
    }

    /** @return 'ok'|'invalid'|'too_fast' */
    public function verifyFormToken(?string $token): string
    {
        if (! is_string($token) || $token === '') {
            return 'invalid';
        }

        try {
            $payload = json_decode(Crypt::decryptString($token), true, 2, JSON_THROW_ON_ERROR);
            $issuedAt = $payload['issued_at'] ?? null;
            $nonce = $payload['nonce'] ?? null;
        } catch (DecryptException|\JsonException) {
            return 'invalid';
        }

        if (! is_int($issuedAt) || ! is_string($nonce) || ! preg_match('/^[a-f0-9]{32}$/', $nonce)) {
            return 'invalid';
        }

        $age = now()->getTimestamp() - $issuedAt;

        if ($age < 0 || $age > self::TOKEN_TTL_SECONDS) {
            return 'invalid';
        }

        return $age < self::MINIMUM_FILL_SECONDS ? 'too_fast' : 'ok';
    }

    public function fingerprint(array $data): string
    {
        return hash('sha256', implode("\n", [
            $this->normaliseText($data['name']),
            strtolower(trim($data['email'])),
            $this->normaliseText($data['subject']),
            $this->normaliseText($data['message']),
        ]));
    }

    public function hashIp(Request $request): string
    {
        return hash_hmac('sha256', (string) $request->ip(), (string) config('app.key'));
    }

    public function log(string $outcome, Request $request): void
    {
        Log::channel('contact_antispam')->info('Contact submission anti-spam outcome.', [
            'outcome' => $outcome,
            'ip_hash' => $this->hashIp($request),
        ]);
    }

    private function normaliseText(string $value): string
    {
        return preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
    }
}
