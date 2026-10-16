<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class AnalyticsSettings
{
    public const ENABLED_KEY = 'analytics.ga4.enabled';
    public const MEASUREMENT_ID_KEY = 'analytics.ga4.measurement_id';

    private const CACHE_KEY = 'analytics.ga4.settings';

    /** @return array{enabled: bool, measurement_id: ?string} */
    public function values(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            $settings = Setting::query()
                ->whereIn('key', [self::ENABLED_KEY, self::MEASUREMENT_ID_KEY])
                ->pluck('value', 'key');

            return [
                'enabled' => (bool) ($settings[self::ENABLED_KEY]['enabled'] ?? false),
                'measurement_id' => self::normalizeMeasurementId($settings[self::MEASUREMENT_ID_KEY]['value'] ?? null),
            ];
        });
    }

    public function enabled(): bool
    {
        return $this->values()['enabled'];
    }

    public function measurementId(): ?string
    {
        $measurementId = $this->values()['measurement_id'];

        return self::isValidMeasurementId($measurementId) ? $measurementId : null;
    }

    public function isConfigured(): bool
    {
        return $this->enabled() && $this->measurementId() !== null;
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function normalizeMeasurementId(?string $measurementId): ?string
    {
        $measurementId = strtoupper((string) preg_replace('/\s+/', '', $measurementId ?? ''));

        return $measurementId === '' ? null : $measurementId;
    }

    public static function isValidMeasurementId(?string $measurementId): bool
    {
        return is_string($measurementId)
            && preg_match('/\AG-[A-Z0-9]{4,32}\z/', $measurementId) === 1;
    }
}
