<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $admin = $request->is('admin*');

        $policy = [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "object-src 'none'",
            "img-src 'self' data: https://*.tile.openstreetmap.org https://www.googletagmanager.com https://*.google-analytics.com".($admin ? ' https://ui-avatars.com' : ''),
            "font-src 'self' data:",
            "connect-src 'self' https://www.googletagmanager.com https://*.google-analytics.com https://*.google.com",
            // Filament 5 evaluates its component expressions through Alpine.
            // Keep this compatibility exception inside the back-office only.
            "script-src 'self' 'unsafe-inline'".($admin ? " 'unsafe-eval' https://unpkg.com" : ''),
            "script-src-elem 'self' https://www.googletagmanager.com".($admin ? ' https://unpkg.com' : ''),
            "style-src 'self' 'unsafe-inline'".($admin ? ' https://unpkg.com' : ''),
        ];

        $response->headers->set('Content-Security-Policy', implode('; ', $policy));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), geolocation=(), microphone=()');

        if (! app()->environment('production') || $request->getHost() !== parse_url((string) config('app.url'), PHP_URL_HOST)) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        if (app()->environment('production') && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
