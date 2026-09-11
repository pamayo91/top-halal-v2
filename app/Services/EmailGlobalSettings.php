<?php

namespace App\Services;

use App\Models\{MediaAsset, Setting};

class EmailGlobalSettings
{
    public const KEY = 'email_global_settings';

    public function values(): array
    {
        $stored = (array) (Setting::query()->where('key', self::KEY)->value('value') ?? []);

        return array_replace($this->defaults(), $stored);
    }

    public function update(array $values): void
    {
        $values = array_replace($this->defaults(), $values);
        $values['show_current_year'] = (bool) ($values['show_current_year'] ?? true);
        $values['logo_media_asset_id'] = filled($values['logo_media_asset_id'] ?? null) ? (int) $values['logo_media_asset_id'] : null;
        $values['primary_color'] = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $values['primary_color']) ? $values['primary_color'] : $this->defaults()['primary_color'];

        Setting::updateOrCreate(['key' => self::KEY], ['group' => 'email', 'value' => $values]);
    }

    public function forRender(): array
    {
        $values = $this->values();
        $values['primary_color'] = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $values['primary_color']) ? $values['primary_color'] : $this->defaults()['primary_color'];
        $logo = $values['logo_media_asset_id'] ? MediaAsset::find($values['logo_media_asset_id']) : null;

        return $values + [
            'logo_url' => $logo?->isRestaurantImage() ? $logo->deliveryUrl(480) : null,
            'year' => $values['show_current_year'] ? now()->year : null,
        ];
    }

    private function defaults(): array
    {
        return [
            'display_name' => 'Top Halal',
            'logo_media_asset_id' => null,
            'primary_color' => '#0b5d4b',
            'footer_text' => 'Top Halal',
            'show_current_year' => true,
            'footer_additional_text' => null,
        ];
    }
}
