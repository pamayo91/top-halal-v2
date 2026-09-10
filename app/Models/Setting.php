<?php

namespace App\Models;

use App\Services\{CitySeoService, NearbyCityService, PublicNavigation};
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['value' => 'array']; }

    protected static function booted(): void
    {
        $invalidateNearbyCities = function (self $setting): void {
            if ($setting->key === 'city_seo_minimum_restaurants') {
                app(CitySeoService::class)->forget();

                return;
            }

            if (in_array($setting->key, ['city_nearby_radius_km', 'city_nearby_maximum'], true)) {
                app(NearbyCityService::class)->forget();
            }

            if (in_array($setting->key, ['header_navigation', 'footer_navigation'], true)) {
                app(PublicNavigation::class)->forget();
            }
        };

        static::saved($invalidateNearbyCities);
        static::deleted($invalidateNearbyCities);
    }
}
