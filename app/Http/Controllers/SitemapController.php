<?php

namespace App\Http\Controllers;

use App\Models\{Article, CitySpecialtySeoPage, Page, Restaurant};
use App\Services\{CityPageResolver, CitySeoService, CitySpecialtySeoService, GeographicPageResolver};
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $citySeo = app(CitySeoService::class);
        $cities = app(CityPageResolver::class);
        $citySpecialties = app(CitySpecialtySeoService::class);
        $geography = app(GeographicPageResolver::class);
        $threshold = $citySeo->threshold();
        $urls = collect([['loc' => route('home'), 'lastmod' => null]])
            ->merge(Restaurant::where('status', 'published')->where(fn ($query) => $query->whereNull('seo_robots')->orWhere(fn ($robots) => $robots->where('seo_robots', 'not like', '%noindex%')->where('seo_robots', 'not like', '%none%')))->get()->map(fn ($x) => ['loc' => route('restaurants.show', $x->slug), 'lastmod' => $x->updated_at]))
            ->merge($citySeo->cities()->filter(fn ($city) => $city->open)->map(fn ($city) => ['loc' => route('cities.show', $city->slug), 'lastmod' => $city->config?->updated_at]))
            ->merge(CitySpecialtySeoPage::query()->with('category')->where('state', 'open')->get()->map(function (CitySpecialtySeoPage $facet) use ($cities, $citySpecialties): ?array {
                $city = $cities->cityForCode($facet->city_code);
                return $city === null || $facet->category === null ? null : ['loc' => $citySpecialties->url($city, $facet->category), 'lastmod' => $facet->updated_at];
            })->filter())
            ->merge($geography->departments()->filter(fn ($department) => $department->restaurants_count >= $threshold)->map(fn ($department) => ['loc' => route('cities.show', $department->slug), 'lastmod' => null]))
            ->merge($geography->regions()->filter(fn ($region) => $region->restaurants_count >= $threshold)->map(fn ($region) => ['loc' => route('cities.show', $region->slug), 'lastmod' => null]))
            ->merge(Article::where('status', 'published')->where(fn ($query) => $query->whereNull('seo_robots')->orWhere('seo_robots', 'not like', '%noindex%'))->get()->map(fn ($x) => ['loc' => route('editorial.show', $x->slug), 'lastmod' => $x->legacy_modified_at ?? $x->updated_at]))
            ->merge(Page::where('status', 'published')->where(fn ($query) => $query->whereNull('seo_robots')->orWhere('seo_robots', 'not like', '%noindex%'))->get()->map(fn ($x) => ['loc' => route('editorial.show', $x->slug), 'lastmod' => $x->legacy_modified_at ?? $x->updated_at]))
            ->unique('loc')
            ->values();
        return response()->view('seo.sitemap', compact('urls'))->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
