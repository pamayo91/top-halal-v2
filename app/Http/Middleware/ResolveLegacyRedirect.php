<?php

namespace App\Http\Middleware;

use App\Services\RedirectResolver;
use Closure;
use Illuminate\Http\Request;

class ResolveLegacyRedirect
{
    public function handle(Request $request, Closure $next): mixed
    {
        // V2 discovery endpoints intentionally supersede an obsolete legacy fallback rule.
        // Authentication and account routes are application endpoints, never
        // historical content. Resolving them through the legacy table can
        // break the login flow before Laravel has a chance to authenticate.
        if (
            in_array($request->path(), ['restaurants', 'restaurants/autour-de-moi', 'login', 'register', 'forgot-password', 'change-password', 'verify-email'], true)
            || $request->is('restaurants/recherche', 'restaurants/recherche/*')
            || $request->is('admin', 'admin/*', 'bo', 'bo/*', 'reset-password/*', 'verify-email/*', 'email/verification-notification', 'account', 'account/*', 'claims', 'claims/*')
        ) return $next($request);
        $resolved = app(RedirectResolver::class)->resolve($request);
        if (! $resolved) return $next($request);
        return $resolved['status'] === 410
            ? response()->view('errors.410', [], 410)
            : redirect($resolved['destination'], $resolved['status']);
    }
}
