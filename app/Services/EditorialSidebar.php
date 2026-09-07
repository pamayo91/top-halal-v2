<?php

namespace App\Services;

use App\Models\{Article, Page, Setting};
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Str;

class EditorialSidebar
{
    public const TYPES = ['toc', 'search', 'restaurants', 'articles', 'correction', 'near_me', 'featured', 'explore', 'share', 'contact'];

    /** @return array{enabled: bool, blocks: array<int, array<string, mixed>>, html: string, toc: array<int, array<string, mixed>>} */
    public function for(Article|Page $content): array
    {
        $kind = $content instanceof Article ? 'articles' : 'pages';
        $enabled = $content->editorial_sidebar_enabled;
        $enabled = $enabled === null ? $content instanceof Article : (bool) $enabled;
        $blocks = $this->merge($this->global($kind), (array) ($content->editorial_sidebar_overrides ?? []));
        [$html, $toc] = $this->headings((string) $content->content_html);

        return compact('enabled', 'blocks', 'html', 'toc');
    }

    /** @return array<int, array<string, mixed>> */
    public function global(string $kind): array
    {
        $value = Setting::query()->where('key', "editorial_sidebar_{$kind}")->first()?->value;
        return $this->normalize(is_array($value) ? ($value['blocks'] ?? []) : []);
    }

    /** @param array<int, mixed> $global @param array<string, mixed> $overrides */
    public function merge(array $global, array $overrides): array
    {
        $byType = collect($global)->keyBy('type');
        foreach ((array) ($overrides['blocks'] ?? []) as $override) {
            if (!is_array($override) || !in_array($override['type'] ?? null, self::TYPES, true)) continue;
            $type = $override['type'];
            $byType[$type] = array_filter(array_merge($byType[$type] ?? $this->defaultBlock($type), $override), fn ($value) => $value !== null && $value !== '');
        }
        return $byType->sortBy('order')->values()->all();
    }

    /** @param array<int, mixed> $blocks */
    public function normalize(array $blocks): array
    {
        $blocks = collect($blocks)->filter(fn ($block) => is_array($block) && in_array($block['type'] ?? null, self::TYPES, true))
            ->map(function (array $block, int $index): array {
                $base = $this->defaultBlock($block['type']);
                $block = array_merge($base, $block);
                $block['enabled'] = filter_var($block['enabled'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $base['enabled'];
                $block['limit'] = max(1, min(6, (int) ($block['limit'] ?: $base['limit'])));
                $block['order'] = (int) ($block['order'] ?? $index + 1);
                return $block;
            })->values();

        return $blocks->isNotEmpty() ? $blocks->sortBy('order')->values()->all() : array_map(fn ($type, $order) => $this->defaultBlock($type, $order + 1), self::TYPES, array_keys(self::TYPES));
    }

    /** @return array<string, mixed> */
    public function defaultBlock(string $type, int $order = 1): array
    {
        $titles = ['toc' => 'Sommaire', 'search' => 'Rechercher un restaurant', 'restaurants' => 'Restaurants liés au sujet', 'articles' => 'Articles liés', 'correction' => 'Une information à corriger ?', 'near_me' => 'Trouver un restaurant halal autour de vous', 'featured' => 'Restaurants à la une', 'explore' => 'Explorer aussi', 'share' => 'Partager', 'contact' => 'Nous contacter'];
        return ['type' => $type, 'enabled' => in_array($type, ['toc', 'search', 'articles', 'correction', 'near_me', 'share'], true), 'title' => $titles[$type], 'order' => $order, 'limit' => 3, 'mode' => 'auto', 'ids' => '', 'links' => ''];
    }

    /** @return array{0:string,1:array<int,array{level:int,id:string,label:string}>} */
    private function headings(string $html): array
    {
        if (trim($html) === '') return [$html, []];
        libxml_use_internal_errors(true);
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->loadHTML('<?xml encoding="utf-8" ?><div id="editorial-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $xpath = new DOMXPath($document);
        $nodes = $xpath->query('//*[@id="editorial-root"]//*[self::h2 or self::h3]');
        $seen = []; $toc = [];
        foreach ($nodes ?: [] as $node) {
            if (!$node instanceof DOMElement) continue;
            $label = trim($node->textContent);
            if ($label === '') continue;
            $base = Str::slug($label) ?: 'section';
            $seen[$base] = ($seen[$base] ?? 0) + 1;
            $id = $seen[$base] === 1 ? $base : $base.'-'.$seen[$base];
            $node->setAttribute('id', $id);
            $toc[] = ['level' => (int) substr($node->tagName, 1), 'id' => $id, 'label' => $label];
        }
        $root = $document->getElementById('editorial-root');
        $rendered = '';
        foreach ($root?->childNodes ?? [] as $child) $rendered .= $document->saveHTML($child);
        return [$rendered, count(array_filter($toc, fn ($item) => $item['level'] === 2)) >= 2 ? $toc : []];
    }
}
