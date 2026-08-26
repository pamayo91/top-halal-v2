<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CanonicalisePublicUrl
{
    /** Public content uses slashless URLs. Files and the root are intentionally untouched. */
    public function handle(Request $request, Closure $next): mixed
    {
        $path = $request->getPathInfo();
        if ($path !== '/' && str_ends_with($path, '/') && ! str_contains(basename($path), '.')) {
            $query = $request->getQueryString();
            return redirect(rtrim($path, '/').($query ? '?'.$query : ''), 301);
        }
        return $next($request);
    }
}
