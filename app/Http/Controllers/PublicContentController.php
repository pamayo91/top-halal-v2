<?php

namespace App\Http\Controllers;

use App\Models\{Article, Category, Feature, Location, Page, Restaurant};
use Illuminate\Http\Response;

class PublicContentController extends Controller
{
    public function restaurant(string $slug): Response
    {
        $restaurant = Restaurant::where('slug', $slug)->where('status', 'published')->firstOrFail();
        $reviews = $restaurant->reviews()->where('status', 'approved')->orderBy('created_at')->get();
        return response()->view('public.restaurant', compact('restaurant', 'reviews'), 200);
    }
    public function location(string $slug): Response { return $this->taxonomy(Location::where('slug', $slug)->firstOrFail(), 'ville'); }
    public function category(string $slug): Response { return $this->taxonomy(Category::where('slug', $slug)->firstOrFail(), 'spécialité'); }
    public function feature(string $slug): Response { return $this->taxonomy(Feature::where('slug', $slug)->firstOrFail(), 'service'); }
    public function editorial(string $slug): Response
    {
        $content = Page::where('slug', $slug)->where('status', 'published')->first()
            ?? Article::where('slug', $slug)->where('status', 'published')->firstOrFail();
        return response()->view('public.editorial', compact('content'), 200);
    }
    private function taxonomy(object $term, string $kind): Response { return response()->view('public.taxonomy', compact('term', 'kind'), 200); }
}
