<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommentRequest;
use App\Http\Requests\StoreRestaurantReviewRequest;
use App\Models\{Article, Category, Comment, Feature, Location, Page, Restaurant, RestaurantReview};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\{RedirectResponse, Request, Response};
use Illuminate\Support\Str;
use Illuminate\View\View;

class PublicContentController extends Controller
{
    public function home(): View
    {
        return view('public.home', [
            'featuredRestaurants' => $this->publishedRestaurants()->latest('legacy_published_at')->limit(6)->get(),
            'cities' => Location::whereHas('restaurants', fn (Builder $q) => $q->where('status', 'published'))->orderBy('name')->limit(12)->get(),
            'categories' => Category::whereHas('restaurants', fn (Builder $q) => $q->where('status', 'published'))->orderBy('name')->limit(10)->get(),
            'articles' => Article::with(['categories', 'featuredMedia.asset.variants'])->where('status', 'published')->orderByDesc('published_at')->orderByDesc('legacy_published_at')->limit(3)->get(),
        ]);
    }

    public function index(Request $request): View
    {
        return view('public.restaurants.index', [
            'restaurants' => $this->applySearch($this->publishedRestaurants(), $request)->paginate(12)->withQueryString(),
            'categories' => Category::orderBy('name')->get(), 'features' => Feature::orderBy('name')->get(),
            'locations' => Location::whereHas('restaurants', fn (Builder $q) => $q->where('status', 'published'))->orderBy('name')->get(),
            'hasFilters' => $request->filled(['q', 'ville']) || $request->filled('categories') || $request->filled('features') || $request->filled(['lat', 'lng']),
        ]);
    }

    public function nearMe(Request $request): RedirectResponse
    {
        $data = $request->validate(['lat' => ['required', 'numeric', 'between:-90,90'], 'lng' => ['required', 'numeric', 'between:-180,180']]);
        return redirect()->route('restaurants.index', ['lat' => round((float) $data['lat'], 5), 'lng' => round((float) $data['lng'], 5)]);
    }

    public function blog(Request $request): View
    {
        $category = trim((string) $request->query('categorie'));
        $categories = \App\Models\EditorialCategory::query()->whereHas('articles', fn ($query) => $query->where('status', 'published'))->orderBy('name')->get();
        $articles = Article::query()->with(['categories', 'featuredMedia.asset.variants'])->where('status', 'published')->when($category !== '', fn ($query) => $query->whereHas('categories', fn ($categories) => $categories->where('slug', $category)))->orderByDesc('published_at')->orderByDesc('legacy_published_at')->paginate(12)->withQueryString();

        return view('public.blog.index', compact('articles', 'categories', 'category'));
    }

    public function muslimGourmet(): View
    {
        return $this->blog(new Request(['categorie' => 'muslim-gourmet']));
    }

    public function restaurant(string $slug): Response
    {
        $restaurant = $this->publishedRestaurants()->where('slug', $slug)->firstOrFail();
        $reviews = $restaurant->reviews()->where('status', 'approved')->oldest()->get();
        return response()->view('public.restaurant', compact('restaurant', 'reviews'));
    }

    public function storeReview(StoreRestaurantReviewRequest $request, string $slug): RedirectResponse
    {
        $restaurant = Restaurant::where('slug', $slug)->where('status', 'published')->firstOrFail();
        RestaurantReview::create(['restaurant_id' => $restaurant->id, 'author_name' => $request->validated('name'), 'author_email' => $request->validated('email'), 'rating' => $request->validated('rating'), 'title' => $request->validated('title'), 'content' => trim(strip_tags($request->validated('content'))), 'status' => 'pending']);
        return back()->with('review_submitted', true);
    }

    public function location(string $slug): Response { return $this->taxonomy(Location::where('slug', $slug)->firstOrFail(), 'ville'); }
    public function category(string $slug): Response { return $this->taxonomy(Category::where('slug', $slug)->firstOrFail(), 'spécialité'); }
    public function feature(string $slug): Response { return $this->taxonomy(Feature::where('slug', $slug)->firstOrFail(), 'service'); }

    public function editorial(string $slug): Response
    {
        $content = Page::where('slug', $slug)->where('status', 'published')->first() ?? Article::where('slug', $slug)->where('status', 'published')->firstOrFail();
        $comments = $content->comments()->where('status', 'approved')->oldest()->get();
        $isArticle = $content instanceof Article;
        return response()->view('public.editorial', compact('content', 'comments', 'isArticle'));
    }

    public function storeComment(StoreCommentRequest $request, string $slug): RedirectResponse
    {
        $content = Page::where('slug', $slug)->where('status', 'published')->first() ?? Article::where('slug', $slug)->where('status', 'published')->firstOrFail();
        Comment::create([$content instanceof Page ? 'page_id' : 'article_id' => $content->id, 'author_name' => $request->validated('name'), 'author_email' => $request->validated('email'), 'content' => trim(strip_tags($request->validated('content'))), 'status' => 'pending']);
        return back()->with('comment_submitted', true);
    }

    private function taxonomy(object $term, string $kind): Response
    {
        $query = $this->publishedRestaurants();
        match (true) {
            $term instanceof Location => $query->whereHas('locations', fn (Builder $q) => $q->whereKey($term->id)),
            $term instanceof Category => $query->whereHas('categories', fn (Builder $q) => $q->whereKey($term->id)),
            $term instanceof Feature => $query->whereHas('features', fn (Builder $q) => $q->whereKey($term->id)),
        };
        return response()->view('public.taxonomy', ['term' => $term, 'kind' => $kind, 'restaurants' => $query->paginate(12)->withQueryString()]);
    }

    private function publishedRestaurants(): Builder { return Restaurant::where('status', 'published')->with(['categories', 'features', 'locations', 'media.asset.variants', 'outboundLinks' => fn ($q) => $q->where('is_active', true)]); }

    private function applySearch(Builder $query, Request $request): Builder
    {
        if ($q = trim((string) $request->input('q'))) { $escaped = addcslashes(Str::lower($q), '%_\\'); $query->where(fn (Builder $search) => $search->whereRaw('LOWER(name) LIKE ?', ["%{$escaped}%"])->orWhereRaw('LOWER(city_name) LIKE ?', ["%{$escaped}%"])); }
        if ($city = $request->input('ville')) $query->where(fn (Builder $q) => $q->where('city_name', $city)->orWhereHas('locations', fn (Builder $locations) => $locations->where('slug', $city)));
        foreach (array_filter((array) $request->input('categories', []), 'is_string') as $slug) $query->whereHas('categories', fn (Builder $q) => $q->where('slug', $slug));
        foreach (array_filter((array) $request->input('features', []), 'is_string') as $slug) $query->whereHas('features', fn (Builder $q) => $q->where('slug', $slug));
        if ($request->filled(['lat', 'lng'])) { $lat = (float) $request->input('lat'); $lng = (float) $request->input('lng'); $distance = '(6371 * acos(least(1, cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))))'; $query->whereNotNull('latitude')->whereNotNull('longitude')->where('proximity_status','ELIGIBLE')->select('restaurants.*')->selectRaw("{$distance} as distance_km", [$lat, $lng, $lat])->orderBy('distance_km'); } else $query->orderBy('name');
        return $query;
    }
}
