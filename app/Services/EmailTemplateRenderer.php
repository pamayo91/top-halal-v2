<?php
namespace App\Services;
use App\Models\EmailTemplate;

class EmailTemplateRenderer
{
    public function __construct(private EmailTemplateRegistry $registry) {}
    public function render(string $key, array $values): array
    {
        $default = $this->registry->get($key); $override = EmailTemplate::where('key', $key)->first();
        $allowed = array_flip($default['variables']); $safe = [];
        foreach ($allowed as $variable => $_) $safe[$variable] = (string) ($values[$variable] ?? '');
        $replace = fn (?string $text) => preg_replace_callback('/{{\s*([a-z_]+)\s*}}/', fn ($m) => array_key_exists($m[1], $safe) ? $safe[$m[1]] : $m[0], $this->normaliseLineBreaks((string) $text));
        $body = $replace($override?->body ?? $default['body']);

        return ['subject' => $replace($override?->subject ?? $default['subject']), 'body' => $body, 'body_paragraphs' => $this->bodyParagraphs($body), 'cta_label' => $replace($override?->cta_label ?? $default['cta_label']), 'cta_url' => $safe['action_url'] ?? $safe['verification_url'] ?? $safe['reset_url'] ?? null];
    }

    /** Converts historical literal escape sequences and all newline styles to real line feeds. */
    private function normaliseLineBreaks(string $text): string
    {
        return str_replace(["\\r\\n", "\\n", "\r\n", "\r"], "\n", $text);
    }

    /** @return array<int, array<int, string>> Plain-text paragraphs and their single-line breaks. */
    public function bodyParagraphs(string $body): array
    {
        $paragraphs = preg_split('/\n{2,}/', trim($this->normaliseLineBreaks($body))) ?: [];

        return array_values(array_map(fn (string $paragraph): array => explode("\n", $paragraph), $paragraphs));
    }
}
