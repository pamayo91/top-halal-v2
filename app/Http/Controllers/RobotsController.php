<?php

namespace App\Http\Controllers;

class RobotsController extends Controller
{
    public function __invoke()
    {
        $body = app()->environment('production') ? "User-agent: *\nAllow: /\nSitemap: ".route('sitemap')."\n" : "User-agent: *\nDisallow: /\n";
        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
