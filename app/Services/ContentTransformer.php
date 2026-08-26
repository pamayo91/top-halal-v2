<?php

namespace App\Services;

class ContentTransformer
{
    public function transform(string $html): array
    {
        preg_match_all('/\[(vc_[a-z0-9_]+)/i', $html, $matches);
        $shortcodes = array_values(array_unique($matches[1] ?? []));
        $unknown = array_values(array_diff($shortcodes, ['vc_row', 'vc_column', 'vc_column_text', 'vc_message', 'vc_widget_sidebar', 'vc_raw_html']));
        $html = preg_replace_callback('/\[caption\b[^\]]*\](.*?)\[\/caption\]/is', function (array $match): string { $body = trim($match[1]); preg_match('/(<(?:a\b[^>]*>\s*)?<img\b[^>]*>(?:\s*<\/a>)?)(.*)/is', $body, $parts); $caption = trim($parts[2] ?? ''); return '<figure class="legacy-caption">'.($parts[1] ?? $body).($caption !== '' ? '<figcaption>'.$caption.'</figcaption>' : '').'</figure>'; }, $html);
        $html = preg_replace('/\[\/?vc_(?:row|column)(?:\s[^\]]*)?\]/i', '', $html);
        $html = preg_replace('/\[vc_column_text(?:\s[^\]]*)?\](.*?)\[\/vc_column_text\]/is', '$1', $html);
        $html = preg_replace('/\[vc_message(?:\s[^\]]*)?\](.*?)\[\/vc_message\]/is', '<aside class="legacy-message">$1</aside>', $html);
        $html = preg_replace('/\[vc_widget_sidebar[^\]]*\]/i', '', $html);
        $html = preg_replace_callback('/\[vc_raw_html[^\]]*\](.*?)\[\/vc_raw_html\]/is', fn (array $match) => htmlspecialchars_decode(trim($match[1]), ENT_QUOTES | ENT_HTML5), $html);
        return ['html' => $html, 'shortcodes' => $shortcodes, 'unknown' => $unknown, 'removed_sidebar' => in_array('vc_widget_sidebar', $shortcodes, true)];
    }
}
