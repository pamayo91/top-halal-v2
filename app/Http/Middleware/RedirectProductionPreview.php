<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectProductionPreview
{
    /**
     * The production preview shares the live application and database, so it
     * must not remain an alternate public entry point once cutover is complete.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (strtolower($request->getHost()) === 'prod-preview.top-halal.fr') {
            return redirect()->away(rtrim((string) config('app.url'), '/').$request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
