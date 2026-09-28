<?php

namespace App\Services;

use Illuminate\Support\Str;

class CommuneTextNormalizer
{
    /**
     * Stable search key: case and diacritics are folded; spaces, hyphens and
     * common apostrophes become one separator.
     */
    public function normalize(string $value): string
    {
        $value = Str::ascii(Str::lower(trim($value)));
        $value = str_replace(["'", '’', 'ʼ', '`'], '-', $value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';

        return trim($value, '-');
    }
}
