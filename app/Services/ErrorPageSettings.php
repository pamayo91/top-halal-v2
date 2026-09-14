<?php

namespace App\Services;

use App\Models\{MediaAsset, Setting};

class ErrorPageSettings
{
    public const DEFAULT_TITLE = "Cette page n'est plus au menu";
    public const DEFAULT_TEXT = "Impossible de retrouver cette page. Mais pas d'inquiétude : vous pouvez continuer votre recherche";
    public const DEFAULT_ILLUSTRATION_KEY = 'error_404_default_illustration';
    public const SETTINGS_KEY = 'error_404';

    /** @return array{title: string, text: string, illustration: ?MediaAsset} */
    public function values(): array
    {
        $settings = (array) Setting::query()->where('key', self::SETTINGS_KEY)->value('value');
        $fallback = (array) Setting::query()
            ->where('key', self::DEFAULT_ILLUSTRATION_KEY)
            ->value('value');
        $illustrationId = $settings['illustration_media_asset_id'] ?? ($fallback['media_asset_id'] ?? null);

        $illustration = $illustrationId ? MediaAsset::query()
            ->with(['variants' => fn ($query) => $query->where('format', 'webp')->orderBy('width')])
            ->whereKey($illustrationId)
            ->whereIn('mime', MediaAsset::RESTAURANT_IMAGE_MIMES)
            ->first() : null;

        return [
            'title' => trim((string) ($settings['title'] ?? '')) ?: self::DEFAULT_TITLE,
            'text' => trim((string) ($settings['text'] ?? '')) ?: self::DEFAULT_TEXT,
            'illustration' => $illustration,
        ];
    }
}
