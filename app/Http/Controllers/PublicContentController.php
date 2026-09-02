<?php

namespace App\Http\Controllers;

use App\Filament\Resources\{ArticleResource, PageResource, RestaurantResource};
use App\Http\Requests\StoreCommentRequest;
use App\Http\Requests\StoreRestaurantReviewRequest;
use App\Models\{Article, Category, Comment, Feature, Location, Page, Restaurant, RestaurantReview};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\{RedirectResponse, Request, Response};
use Illuminate\Support\Str;
use Illuminate\View\View;
use App\Services\PublicRestaurantSearch;

class PublicContentController extends Controller
{
    public function __construct(private readonly PublicRestaurantSearch $search) {}
    public function home(): View
    {
        return view('public.home', [
            'featuredRestaurants' => $this->search->published()->latest('legacy_published_at')->limit(6)->get(),
            'cities' => $this->topCities(),
            'categories' => Category::whereHas('restaurants', fn (Builder $q) => $q->where('status', 'published'))->orderBy('name')->limit(10)->get(),
            'articles' => Article::with(['categories', 'featuredMedia.asset.variants', 'contentMedia.asset.variants'])->where('status', 'published')->orderByDesc('published_at')->orderByDesc('legacy_published_at')->limit(3)->get(),
        ]);
    }

    public function index(Request $request): View
    {
        return view('public.restaurants.index', [
            'restaurants' => $this->search->apply($this->search->published(), $request)->paginate(12)->withQueryString(),
            'categories' => Category::orderBy('name')->get(), 'features' => Feature::orderBy('name')->get(),
            'locations' => Location::whereHas('restaurants', fn (Builder $q) => $q->where('status', 'published'))->orderBy('name')->get(),
            'hasFilters' => $request->filled(['q', 'ville']) || $request->filled('categories') || $request->filled('features') || $request->filled(['lat', 'lng']),
        ]);
    }

    public function search(Request $request): RedirectResponse
    {
        $city = trim((string) $request->query('ville'));
        $query = trim((string) $request->query('q'));
        $categories = array_values(array_filter((array) $request->query('categories', []), 'is_string'));
        if ($city !== '' && $query === '' && $categories === []) return redirect()->route('locations.show', $city);
        return redirect()->route('restaurants.index', array_filter(['ville' => $city ?: null, 'q' => $query ?: null, 'categories' => $categories ?: null]));
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
        $articles = Article::query()->with(['categories', 'featuredMedia.asset.variants', 'contentMedia.asset.variants'])->where('status', 'published')->when($category !== '', fn ($query) => $query->whereHas('categories', fn ($categories) => $categories->where('slug', $category)))->orderByDesc('published_at')->orderByDesc('legacy_published_at')->paginate(16)->withQueryString();

        return view('public.blog.index', compact('articles', 'categories', 'category'));
    }

    public function muslimGourmet(): View
    {
        return $this->blog(new Request(['categorie' => 'muslim-gourmet']));
    }

    public function restaurant(string $slug): Response
    {
        $restaurant = $this->search->published()->where('slug', $slug)->firstOrFail();
        $reviews = $restaurant->reviews()->where('status', 'approved')->latest('created_at')->get();
        $adminEditUrl = $this->adminEditUrlFor($restaurant);

        return response()->view('public.restaurant', compact('restaurant', 'reviews', 'adminEditUrl'));
    }

    public function storeReview(StoreRestaurantReviewRequest $request, string $slug): RedirectResponse
    {
        $restaurant = Restaurant::where('slug', $slug)->where('status', 'published')->firstOrFail();
        RestaurantReview::create(['restaurant_id' => $restaurant->id, 'author_name' => $request->validated('name'), 'author_email' => $request->validated('email'), 'rating' => $request->validated('rating'), 'title' => $request->validated('title'), 'content' => trim(strip_tags($request->validated('content'))), 'status' => 'pending']);
        return back()->with('review_submitted', true);
    }

    public function location(string $slug): Response
    {
        $city = Restaurant::query()->where('status', 'published')->whereNotNull('city_name')->get(['city_name'])->first(fn ($restaurant) => Str::slug($restaurant->city_name) === $slug);
        if ($city) return response()->view('public.taxonomy', ['term' => (object) ['name' => $city->city_name], 'kind' => 'ville', 'restaurants' => $this->search->published()->where('city_name', $city->city_name)->paginate(12)]);
        return $this->taxonomy(Location::where('slug', $slug)->firstOrFail(), 'ville');
    }
    public function category(string $slug): Response { return $this->taxonomy(Category::where('slug', $slug)->firstOrFail(), 'spécialité'); }
    public function feature(string $slug): Response { return $this->taxonomy(Feature::where('slug', $slug)->firstOrFail(), 'service'); }

    public function editorial(string $slug): Response
    {
        $content = Page::where('slug', $slug)->where('status', 'published')->first() ?? Article::with('featuredMedia.asset')->where('slug', $slug)->where('status', 'published')->firstOrFail();
        $comments = $content->comments()->where('status', 'approved')->latest('created_at')->get();
        $isArticle = $content instanceof Article;
        $adminEditUrl = $this->adminEditUrlFor($content);

        return response()->view('public.editorial', compact('content', 'comments', 'isArticle', 'adminEditUrl'));
    }

    public function storeComment(StoreCommentRequest $request, string $slug): RedirectResponse
    {
        $content = Page::where('slug', $slug)->where('status', 'published')->first() ?? Article::where('slug', $slug)->where('status', 'published')->firstOrFail();
        Comment::create([$content instanceof Page ? 'page_id' : 'article_id' => $content->id, 'author_name' => $request->validated('name'), 'author_email' => $request->validated('email'), 'content' => trim(strip_tags($request->validated('content'))), 'status' => 'pending']);
        return back()->with('comment_submitted', true);
    }

    private function taxonomy(object $term, string $kind): Response
    {
        $query = $this->search->published();
        match (true) {
            $term instanceof Location => $query->whereHas('locations', fn (Builder $q) => $q->whereKey($term->id)),
            $term instanceof Category => $query->whereHas('categories', fn (Builder $q) => $q->whereKey($term->id)),
            $term instanceof Feature => $query->whereHas('features', fn (Builder $q) => $q->whereKey($term->id)),
        };
        return response()->view('public.taxonomy', ['term' => $term, 'kind' => $kind, 'restaurants' => $query->paginate(12)->withQueryString()]);
    }

    /**
     * Builds a back-office shortcut only for the active administrator already
     * authenticated for this request. Public visitors receive no extra markup,
     * assets, or database query.
     */
    private function adminEditUrlFor(Restaurant|Article|Page $record): ?string
    {
        $user = request()->user();

        if ($user?->role !== 'admin' || $user->status !== 'active') {
            return null;
        }

        return match (true) {
            $record instanceof Restaurant => RestaurantResource::getUrl('edit', ['record' => $record]),
            $record instanceof Article => ArticleResource::getUrl('edit', ['record' => $record]),
            $record instanceof Page => PageResource::getUrl('edit', ['record' => $record]),
        };
    }

    private function topCities(): \Illuminate\Support\Collection
    {
        return Restaurant::query()->where('status', 'published')->whereNotNull('city_name')->where('city_name', '!=', '')->selectRaw('city_name, count(*) as restaurants_count')->groupBy('city_name')->orderByDesc('restaurants_count')->limit(11)->get()->map(fn ($city) => ['name' => $city->city_name, 'slug' => Str::slug($city->city_name)])->sortByDesc(fn ($city) => $city['slug'] === 'paris')->values();
    }
}
