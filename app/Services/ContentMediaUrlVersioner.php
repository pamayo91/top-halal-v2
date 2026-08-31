<?php

namespace App\Services;

use App\Models\MediaAsset;

class ContentMediaUrlVersioner
{
    /** @return array{html: string, replaced: int, unresolved_asset_ids: list<int>} */
    public function rewrite(string $html): array
    {
        preg_match_all('#/media/(\d+)(?:/(\d+))?(?!/v/)#', $html, $matches, PREG_SET_ORDER);
        $ids = collect($matches)->map(fn (array $match) => (int) $match[1])->unique()->values();
        if ($ids->isEmpty()) return ['html' => $html, 'replaced' => 0, 'unresolved_asset_ids' => []];

        $assets = MediaAsset::whereIn('id', $ids)->get()->keyBy('id');
        $replaced = 0;
        $unresolved = [];
        $rewritten = preg_replace_callback('#/media/(\d+)(?:/(\d+))?(?!/v/)#', function (array $match) use ($assets, &$replaced, &$unresolved): string {
            $asset = $assets->get((int) $match[1]);
            if (! $asset) {
                $unresolved[] = (int) $match[1];
                return $match[0];
            }

            $replaced++;
            return (string) parse_url($asset->deliveryUrl(isset($match[2]) ? (int) $match[2] : null), PHP_URL_PATH);
        }, $html);

        return ['html' => $rewritten ?? $html, 'replaced' => $replaced, 'unresolved_asset_ids' => array_values(array_unique($unresolved))];
    }
}
