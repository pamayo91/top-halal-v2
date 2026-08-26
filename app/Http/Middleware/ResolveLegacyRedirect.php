<?php

namespace App\Http\Middleware;

use App\Services\RedirectResolver;
use Closure;
use Illuminate\Http\Request;

class ResolveLegacyRedirect
{
    public function handle(Request $request, Closure $next): mixed
    {
        $resolved = app(RedirectResolver::class)->resolve($request);
        if (! $resolved) return $next($request);
        return $resolved['status'] === 410
            ? response()->view('errors.410', [], 410)
            : redirect($resolved['destination'], $resolved['status']);
    }
}
