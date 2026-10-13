<?php

namespace App\Http\Controllers;

class RobotsController extends Controller
{
    public function __invoke()
    {
        $isCanonicalHost = request()->getHost() === parse_url((string) config('app.url'), PHP_URL_HOST);
        $body = app()->environment('production') && $isCanonicalHost
            ? "User-agent: *\nAllow: /\nDisallow: */claim\nDisallow: /login\nDisallow: /forgot-password\nDisallow: /*near_me=*\n"
            : "User-agent: *\nDisallow: /\n";
        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
