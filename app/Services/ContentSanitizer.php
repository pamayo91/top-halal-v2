<?php

namespace App\Services;

class ContentSanitizer
{
    public function sanitize(string $html, bool $removeLegacyImages = true): array
    {
        $removed = [];
        $html = preg_replace_callback('/<script\b[^>]*>.*?<\/script>/is', function ($match) use (&$removed) { $removed[] = 'script'; return ''; }, $html);
        $html = preg_replace_callback('/<iframe\b([^>]*)>.*?<\/iframe>/is', function ($match) use (&$removed) { if (preg_match('/src=["\']https:\/\/(www\.)?(youtube\.com|youtu\.be|vimeo\.com)\//i', $match[1])) return $match[0]; $removed[] = 'iframe'; return ''; }, $html);
        $html = preg_replace_callback('/<(?:object|embed)\b[^>]*>.*?<\/(?:object|embed)>/is', function ($match) use (&$removed) { $removed[] = 'legacy_embed'; return ''; }, $html);
        $html = preg_replace_callback('/<ins\b[^>]*\badsbygoogle\b[^>]*>.*?<\/ins>/is', function () use (&$removed) { $removed[] = 'legacy_adsense'; return ''; }, $html);
        $html = preg_replace_callback('/(?<![A-Za-z0-9+\/=])([A-Za-z0-9+\/]{80,}={0,2})(?![A-Za-z0-9+\/=])/', function ($match) use (&$removed) { $decoded = base64_decode($match[1], true); $decoded = $decoded === false ? '' : urldecode($decoded); if (preg_match('/adsbygoogle|googlesyndication|ca-pub-/i', $decoded)) { $removed[] = 'encoded_legacy_adsense'; return ''; } return $match[0]; }, $html);
        $html = preg_replace_callback('/(?:%[0-9a-f]{2}){12,}/i', function ($match) use (&$removed) { $decoded = urldecode($match[0]); if (preg_match('/adsbygoogle|googlesyndication|ca-pub-/i', $decoded)) { $removed[] = 'encoded_legacy_adsense'; return ''; } return $match[0]; }, $html);
        $html = preg_replace_callback('/JTNDcCUzRS(?=[A-Za-z0-9+\/=]*YWRzYnlnb29nbGU)[A-Za-z0-9+\/=]*?JTNDJTJGcCUzRQ/', function () use (&$removed) { $removed[] = 'encoded_legacy_adsense'; return ''; }, $html);
        if ($removeLegacyImages) {
            $html = preg_replace_callback('/<img\b[^>]*\bsrc=["\'](?:https?:\/\/(www\.)?top-halal\.fr)?\/wp-conten(?:t|u)[^>]*>/i', function () use (&$removed) { $removed[] = 'legacy_image'; return ''; }, $html);
        }
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html);
        $html = preg_replace('/\son\w+\s*=\s*(["\']).*?\1/i', '', $html);
        $html = preg_replace('/\s(?:href|src)\s*=\s*(["\'])\s*javascript:[^\1]*\1/i', '', $html);

        return ['html' => trim($html), 'removed' => $removed];
    }
}
