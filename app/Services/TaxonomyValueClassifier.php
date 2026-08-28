<?php

namespace App\Services;

use Illuminate\Support\Str;

class TaxonomyValueClassifier
{
    /**
     * Classifies data quality without attempting to define an overly narrow
     * whitelist for geographical names.
     */
    public function classify(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return 'empty';
        }

        $normalized = Str::lower($value);
        $maliciousPatterns = [
            '/\bpg_sleep\s*\(/i', '/\bdbms_[a-z_]+\s*\./i', '/\bwaitfor\s+delay\b/i',
            '/\bsleep\s*\(\s*\d+/i', '/\bbenchmark\s*\(/i', '/\bload_file\s*\(/i',
            '/\bunion\s+(all\s+)?select\b/i', '/\bselect\b.{0,80}\bfrom\b/i',
            '/\b(?:or|xor)\b.{0,80}(?:=|\()/i', '/(?:--|\/\*|\*\/).{0,80}(?:select|sleep|or|xor)/i',
            '/@@[a-z_]/i',
        ];

        foreach ($maliciousPatterns as $pattern) {
            if (preg_match($pattern, $normalized) === 1) {
                return 'malicious';
            }
        }

        if (mb_strlen($value) > 120 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value) === 1) {
            return 'suspect';
        }

        return 'valid';
    }

    public function isMalicious(?string $value): bool
    {
        return $this->classify($value) === 'malicious';
    }

    public function normalizedKey(string $value): string
    {
        return (string) Str::of(Str::ascii(Str::lower(trim($value))))
            ->replaceMatches('/[^a-z0-9]+/', '')
            ->substr(0, 255);
    }
}
