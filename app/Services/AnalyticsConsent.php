<?php

namespace App\Services;

use Illuminate\Http\Request;

class AnalyticsConsent
{
    public const COOKIE = 'top_halal_analytics_consent_v1';
    public const UNKNOWN = 'unknown';
    public const GRANTED = 'granted';
    public const DENIED = 'denied';

    public function state(?Request $request = null): string
    {
        $value = ($request ?? request())->cookie(self::COOKIE);

        return in_array($value, [self::GRANTED, self::DENIED], true) ? $value : self::UNKNOWN;
    }
}
