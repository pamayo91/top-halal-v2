<?php

namespace App\Services;

/** Normalises plain-text legacy values without touching rich HTML. */
class TextNormalizer
{
    /** @return array{value:string,changed:bool,ambiguous:bool} */
    public function normalizePlainText(?string $value): array
    {
        $original = (string) $value;
        $normalized = $original;

        // Entity decoding is deliberately limited to plain-text fields.
        for ($i = 0; $i < 2 && preg_match('/&(?:#\d+|#x[\da-f]+|[a-z][a-z\d]+);/i', $normalized); $i++) {
            $decoded = html_entity_decode($normalized, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($decoded === $normalized) break;
            $normalized = $decoded;
        }

        if (! $this->hasMojibake($normalized)) {
            return ['value' => $normalized, 'changed' => $normalized !== $original, 'ambiguous' => false];
        }

        $candidate = $normalized;
        for ($i = 0; $i < 2 && $this->hasMojibake($candidate); $i++) {
            $converted = mb_convert_encoding($candidate, 'UTF-8', 'Windows-1252');
            if (! mb_check_encoding($converted, 'UTF-8') || $this->mojibakeScore($converted) >= $this->mojibakeScore($candidate)) break;
            $candidate = $converted;
        }

        if ($candidate === $normalized) {
            return ['value' => $normalized, 'changed' => $normalized !== $original, 'ambiguous' => true];
        }

        return ['value' => $candidate, 'changed' => $candidate !== $original, 'ambiguous' => false];
    }

    public function plainText(?string $value): string
    {
        return $this->normalizePlainText($value)['value'];
    }

    private function hasMojibake(string $value): bool
    {
        return preg_match('/(?:Ã.|Â.|â€|â€™|â€œ|â€|â€“|â€”)/u', $value) === 1;
    }

    private function mojibakeScore(string $value): int
    {
        return preg_match_all('/(?:Ã.|Â.|â€|â€™|â€œ|â€|â€“|â€”)/u', $value) ?: 0;
    }
}
