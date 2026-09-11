<?php

namespace App\Services;

use App\Models\{MediaAsset, Setting};

class EmailGlobalSettings
{
    public const KEY = 'email_global_settings';

    public function values(): array
    {
        $stored = (array) (Setting::query()->where('key', self::KEY)->value('value') ?? []);

        return array_replace($this->defaults(), array_intersect_key($stored, $this->defaults()));
    }

    public function update(array $values): void
    {
        $values = array_replace($this->defaults(), array_intersect_key($values, $this->defaults()));
        $values['logo_media_asset_id'] = filled($values['logo_media_asset_id'] ?? null) ? (int) $values['logo_media_asset_id'] : null;

        Setting::updateOrCreate(['key' => self::KEY], ['group' => 'email', 'value' => $values]);
    }

    public function forRender(): array
    {
        $values = $this->values();
        $logo = $values['logo_media_asset_id'] ? MediaAsset::find($values['logo_media_asset_id']) : null;
        $footerPresentation = app(PublicNavigation::class)->footer()['introduction'];

        return $values + [
            'logo_url' => $logo?->isRestaurantImage() ? $logo->deliveryUrl(480) : null,
            'footer_presentation' => $footerPresentation,
            'footer_lines' => preg_split('/\r\n|\r|\n/', $values['footer_text']),
            'primary_color' => config('design.primary'),
            'font_stack' => config('design.font_stack'),
            'button_radius' => config('design.button_radius'),
        ];
    }

    private function defaults(): array
    {
        return [
            'display_name' => 'Top Halal',
            'logo_media_asset_id' => null,
            'footer_text' => "Une question ? Notre équipe est à votre écoute.\nÀ très bientôt sur Top Halal !\nL'équipe Top Halal",
        ];
    }
}
