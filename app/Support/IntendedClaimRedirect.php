<?php

namespace App\Support;

use Illuminate\Http\Request;

class IntendedClaimRedirect
{
    public static function pull(Request $request): ?string
    {
        $intended = (string) $request->session()->pull('url.intended', '');
        $path = (string) (parse_url($intended, PHP_URL_PATH) ?: '');
        $host = (string) (parse_url($intended, PHP_URL_HOST) ?: '');

        if (($host === '' || hash_equals($request->getHost(), $host))
            && preg_match('#^/restaurants/\d+/claim$#', $path)) {
            return $intended;
        }

        return null;
    }
}
