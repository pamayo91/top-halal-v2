<?php

namespace App\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class ContributionSpamProtection
{
    private const TOKEN_TTL_SECONDS = 86400;
    private const MINIMUM_FILL_SECONDS = 2;

    public function issueFormToken(): string
    {
        return Crypt::encryptString(json_encode(['issued_at' => now()->timestamp, 'nonce' => bin2hex(random_bytes(16))], JSON_THROW_ON_ERROR));
    }

    /** @return 'ok'|'invalid'|'too_fast' */
    public function verifyFormToken(?string $token): string
    {
        if (! is_string($token) || $token === '') return 'invalid';
        try {
            $payload = json_decode(Crypt::decryptString($token), true, 2, JSON_THROW_ON_ERROR);
            $issuedAt = $payload['issued_at'] ?? null;
            $nonce = $payload['nonce'] ?? null;
        } catch (DecryptException|\JsonException) {
            return 'invalid';
        }
        if (! is_int($issuedAt) || ! is_string($nonce) || ! preg_match('/^[a-f0-9]{32}$/', $nonce)) return 'invalid';
        $age = now()->timestamp - $issuedAt;
        if ($age < 0 || $age > self::TOKEN_TTL_SECONDS) return 'invalid';
        return $age < self::MINIMUM_FILL_SECONDS ? 'too_fast' : 'ok';
    }

    public function hashIp(Request $request): string
    {
        return hash_hmac('sha256', (string) $request->ip(), (string) config('app.key'));
    }

    public function emailHash(string $email): string
    {
        return hash_hmac('sha256', strtolower(trim($email)), (string) config('app.key'));
    }

    public function fingerprint(string $type, string $targetType, int $targetId, string $email, array $payload): string
    {
        return hash('sha256', json_encode([$type, $targetType, $targetId, strtolower(trim($email)), $this->normalise($payload)], JSON_THROW_ON_ERROR));
    }

    public function log(string $outcome, Request $request): void
    {
        Log::channel('contribution_antispam')->info('Contribution submission anti-spam outcome.', ['outcome' => $outcome, 'ip_hash' => $this->hashIp($request)]);
    }

    private function normalise(array $payload): array
    {
        ksort($payload);
        return array_map(fn (mixed $value) => is_string($value) ? preg_replace('/\s+/u', ' ', trim($value)) : $value, $payload);
    }
}
