<?php

namespace App\Http\Controllers;

use App\Models\{Article, Page, Restaurant};
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $urls = collect([['loc' => route('home'), 'lastmod' => null]])
            ->merge(Restaurant::where('status', 'published')->where(fn ($query) => $query->whereNull('seo_robots')->orWhere(fn ($robots) => $robots->where('seo_robots', 'not like', '%noindex%')->where('seo_robots', 'not like', '%none%')))->get()->map(fn ($x) => ['loc' => route('restaurants.show', $x->slug), 'lastmod' => $x->updated_at]))
            ->merge(Article::where('status', 'published')->where(fn ($query) => $query->whereNull('seo_robots')->orWhere('seo_robots', 'not like', '%noindex%'))->get()->map(fn ($x) => ['loc' => route('editorial.show', $x->slug), 'lastmod' => $x->legacy_modified_at ?? $x->updated_at]))
            ->merge(Page::where('status', 'published')->where(fn ($query) => $query->whereNull('seo_robots')->orWhere('seo_robots', 'not like', '%noindex%'))->get()->map(fn ($x) => ['loc' => route('editorial.show', $x->slug), 'lastmod' => $x->legacy_modified_at ?? $x->updated_at]));
        return response()->view('seo.sitemap', compact('urls'))->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
