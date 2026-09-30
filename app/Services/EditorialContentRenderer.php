<?php

namespace App\Services;

use App\Models\Restaurant;
use App\Services\Quick\QuickRestaurantDirectory;

/**
 * Resolves dynamic editorial tokens immediately before public SSR rendering.
 *
 * Stored article HTML remains editorial source content: restaurant data is
 * always read at request time, so names, addresses and publication state do
 * not become stale in an article.
 */
class EditorialContentRenderer
{
    /** @return array{html: string, quick_map: bool, faqs: list<array{question: string, answer: string}>} */
    public function render(string $html): array
    {
        $html = app(EditorialMediaAttachments::class)->render($html);
        $tables = [];
        $quickMaps = 0;
        $faqs = [];
        $faqBlocks = [];
        $html = preg_replace_callback(
            '/(?:<p>\s*)?\[restaurants_table\b(?=[^\]]*\])([^\]]*)\](?:\s*<\/p>)?/iu',
            function (array $match) use (&$tables): string {
                $tables[] = $this->ids($match[1]);

                return '<!-- editorial-restaurants-table-'.(count($tables) - 1).' -->';
            },
            $html,
        ) ?? $html;

        // A malformed token is editorial syntax, never visitor-facing text.
        $html = preg_replace('/\[restaurants_table\b[^\]<\r\n]*(?:\]|(?=<)|$)/iu', '', $html) ?? $html;

        $html = preg_replace_callback(
            '/(?:<p>\s*)?\[quick_restaurants_map\](?:\s*<\/p>)?/iu',
            static function () use (&$quickMaps): string {
                return '<!-- editorial-quick-restaurants-map-'.($quickMaps++).' -->';
            },
            $html,
        ) ?? $html;
        $html = preg_replace('/\[quick_restaurants_map[^\]<\r\n]*(?:\]|(?=<)|$)/iu', '', $html) ?? $html;

        $html = preg_replace_callback(
            '/(?:<p>\s*)?\[faq\](.*?)\[\/faq\](?:\s*<\/p>)?/isu',
            function (array $match) use (&$faqs, &$faqBlocks): string {
                $questions = $this->faqQuestions($match[1]);
                if ($questions === []) return '';

                $faqs = [...$faqs, ...$questions];
                $faqBlocks[] = $questions;

                return '<!-- editorial-faq-'.(count($faqBlocks) - 1).' -->';
            },
            $html,
        ) ?? $html;
        // Editorial syntax is never shown when an author leaves a FAQ incomplete.
        $html = preg_replace('/\[\/?faq\b[^\]<\r\n]*(?:\]|(?=<)|$)/iu', '', $html) ?? $html;
        $html = preg_replace('/\[\/?question\b[^\]<\r\n]*(?:\]|(?=<)|$)/iu', '', $html) ?? $html;

        if ($tables === [] && $quickMaps === 0 && $faqs === []) return ['html' => $html, 'quick_map' => false, 'faqs' => []];

        $restaurants = collect();
        if ($tables !== []) {
            $restaurants = Restaurant::query()
                ->where('status', 'published')
                ->whereIn('id', collect($tables)->flatten()->unique()->all())
                ->get(['id', 'name', 'slug', 'address_line1', 'postal_code', 'city_name'])
                ->keyBy('id');
        }

        foreach ($tables as $index => $ids) {
            $ordered = collect($ids)
                ->map(fn (int $id) => $restaurants->get($id))
                ->filter()
                ->values();
            $replacement = $ordered->isEmpty()
                ? ''
                : view('components.editorial-restaurants-table', ['restaurants' => $ordered])->render();
            $html = str_replace('<!-- editorial-restaurants-table-'.$index.' -->', $replacement, $html);
        }

        if ($quickMaps > 0) {
            $map = app(QuickRestaurantDirectory::class)->mapData();
            $replacement = $map === null ? '' : view('components.editorial-quick-restaurants-map', $map)->render();
            for ($index = 0; $index < $quickMaps; $index++) {
                $html = str_replace('<!-- editorial-quick-restaurants-map-'.$index.' -->', $replacement, $html);
            }
        }

        foreach ($faqBlocks as $index => $questions) {
            $html = str_replace(
                '<!-- editorial-faq-'.$index.' -->',
                view('components.editorial-faq', compact('questions'))->render(),
                $html,
            );
        }

        return ['html' => $html, 'quick_map' => $quickMaps > 0 && $map !== null, 'faqs' => $faqs];
    }

    /** @return list<array{question: string, answer: string}> */
    private function faqQuestions(string $content): array
    {
        preg_match_all('/\[question\b([^\]]*)\](.*?)\[\/question\]/isu', $content, $matches, PREG_SET_ORDER);

        $questions = [];
        foreach ($matches as $match) {
            $attributes = html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (! preg_match('/\btitle\s*=\s*(["\'])(.*?)\1/isu', $attributes, $title)) continue;

            $question = trim($title[2]);
            $answer = app(ContentSanitizer::class)->sanitize(trim($match[2]))['html'];
            if ($question === '' || $answer === '') continue;
            $questions[] = ['question' => $question, 'answer' => $answer];
        }

        return $questions;
    }

    /** @return list<int> */
    private function ids(string $attributes): array
    {
        $attributes = html_entity_decode($attributes, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (! preg_match('/\bids\s*=\s*(["\'])(.*?)\1/iu', $attributes, $match)) return [];

        $ids = [];
        foreach (preg_split('/\s*,\s*/u', trim($match[2])) ?: [] as $value) {
            if (! preg_match('/^[1-9][0-9]*$/', $value)) continue;
            $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false) continue;
            $ids[(string) $id] = (int) $id;
        }

        return array_values($ids);
    }
}
