<?php

namespace App\Http\Controllers;

use App\Filament\Resources\{ArticleResource, PageResource, RestaurantResource};
use App\Http\Requests\StoreCommentRequest;
use App\Http\Requests\StoreRestaurantReviewRequest;
use App\Models\{Article, Category, Comment, Feature, Page, Restaurant, RestaurantReview};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\{RedirectResponse, Request, Response};
use Illuminate\Support\Str;
use Illuminate\View\View;
use App\Services\{CityPageResolver, CitySeoService, GeographicPageResolver, NearbyCityService, PublicRestaurantSearch};

class PublicContentController extends Controller
{
    public function __construct(
        private readonly PublicRestaurantSearch $search,
        private readonly CityPageResolver $cities,
        private readonly CitySeoService $citySeo,
        private readonly GeographicPageResolver $geography,
        private readonly NearbyCityService $nearbyCities,
    ) {}
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
            'locations' => $this->citySeo->cities()->sortBy('city_name')->map(fn (object $city): object => (object) ['name' => $this->cityLabel($city), 'slug' => $city->slug]),
            'hasFilters' => $request->filled(['q', 'ville']) || $request->filled('categories') || $request->filled('features') || $request->filled(['lat', 'lng']),
        ]);
    }

    public function search(Request $request): RedirectResponse
    {
        $city = trim((string) $request->query('ville'));
        $query = trim((string) $request->query('q'));
        $categories = array_values(array_filter((array) $request->query('categories', []), 'is_string'));
        if ($city !== '' && $query === '' && $categories === []) return redirect()->route('cities.show', $city);
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
        $breadcrumbs = $this->restaurantBreadcrumbs($restaurant);

        return response()->view('public.restaurant', compact('restaurant', 'reviews', 'adminEditUrl', 'breadcrumbs'));
    }

    public function storeReview(StoreRestaurantReviewRequest $request, string $slug): RedirectResponse
    {
        $restaurant = Restaurant::where('slug', $slug)->where('status', 'published')->firstOrFail();
        RestaurantReview::create(['restaurant_id' => $restaurant->id, 'author_name' => $request->validated('name'), 'author_email' => $request->validated('email'), 'rating' => $request->validated('rating'), 'title' => $request->validated('title'), 'content' => trim(strip_tags($request->validated('content'))), 'status' => 'pending']);
        return back()->with('review_submitted', true);
    }

    public function location(string $slug): Response
    {
        if ($city = $this->cities->cityForSlug($slug)) {
            $citySeo = $this->citySeo->city($city->city_code);

            return $this->geographicListing(
                term: (object) ['name' => $city->city_name],
                kind: 'ville',
                restaurants: $this->search->published()->whereIn('city_code', $city->source_city_codes)->paginate(12)->withQueryString(),
                open: (bool) $citySeo?->open,
                citySeo: $citySeo,
                breadcrumbs: $this->cityBreadcrumbs($city),
                nearbyCities: $this->nearbyCities->nearbyFor($city),
            );
        }

        if (($cities = $this->cities->ambiguityForSlug($slug))->isNotEmpty()) {
            return response()->view('public.city-disambiguation', [
                'cityName' => $cities->first()->city_name,
                'cities' => $cities,
                'breadcrumbs' => $this->breadcrumbs((object) ['name' => $cities->first()->city_name]),
            ]);
        }

        if ($department = $this->geography->departmentForSlug($slug)) {
            return $this->geographicListing(
                term: (object) ['name' => $department->name],
                kind: 'département',
                restaurants: $this->geography->scopeDepartment($this->search->published(), $department)->paginate(12)->withQueryString(),
                open: $department->restaurants_count >= $this->citySeo->threshold(),
                breadcrumbs: $this->departmentBreadcrumbs($department),
            );
        }

        if ($region = $this->geography->regionForSlug($slug)) {
            return $this->geographicListing(
                term: (object) ['name' => $region->name],
                kind: 'région',
                restaurants: $this->geography->scopeRegion($this->search->published(), $region)->paginate(12)->withQueryString(),
                open: $region->restaurants_count >= $this->citySeo->threshold(),
                breadcrumbs: $this->breadcrumbs((object) ['name' => $region->name]),
            );
        }

        abort(404);
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
            $term instanceof Category => $query->whereHas('categories', fn (Builder $q) => $q->whereKey($term->id)),
            $term instanceof Feature => $query->whereHas('features', fn (Builder $q) => $q->whereKey($term->id)),
        };
        return response()->view('public.taxonomy', [
            'term' => $term,
            'kind' => $kind,
            'restaurants' => $query->paginate(12)->withQueryString(),
            'breadcrumbs' => $this->breadcrumbs($term),
        ]);
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
        return $this->citySeo->cities()
            ->sortByDesc('restaurants_count')
            ->take(11)
            ->map(fn (object $city): array => ['name' => $this->cityLabel($city), 'slug' => $city->slug])
            ->sortByDesc(fn (array $city): bool => $city['slug'] === 'paris')
            ->values();
    }

    private function geographicListing(object $term, string $kind, mixed $restaurants, bool $open, ?object $citySeo = null, array $breadcrumbs = [], mixed $nearbyCities = null): Response
    {
        $name = $term->name;
        $title = $citySeo?->config?->seo_title ?: match ($kind) {
            'ville' => "Restaurants halal à {$name} | Top Halal",
            'département' => "Restaurants halal dans le département {$name} | Top Halal",
            default => "Restaurants halal en {$name} | Top Halal",
        };
        $description = $citySeo?->config?->seo_description ?: "Découvrez {$restaurants->total()} restaurants halal en {$name}.";

        return response()->view('public.taxonomy', compact('term', 'kind', 'restaurants', 'open', 'citySeo', 'breadcrumbs', 'title', 'description', 'nearbyCities'));
    }

    /** @return list<array{label:string,url:?string}> */
    private function breadcrumbs(object $current): array
    {
        return [
            ['label' => 'Accueil', 'url' => route('home')],
            ['label' => 'Restaurants', 'url' => route('restaurants.index')],
            ['label' => $current->name, 'url' => null],
        ];
    }

    /** @return list<array{label:string,url:?string}> */
    private function cityBreadcrumbs(object $city): array
    {
        $breadcrumbs = [
            ['label' => 'Accueil', 'url' => route('home')],
            ['label' => 'Restaurants', 'url' => route('restaurants.index')],
            ['label' => $city->region['name'], 'url' => route('cities.show', $city->region['slug'])],
        ];

        if ($city->department['slug'] !== $city->slug && $city->department['slug'] !== $city->region['slug']) {
            $breadcrumbs[] = ['label' => $city->department['name'], 'url' => route('cities.show', $city->department['slug'])];
        }

        $breadcrumbs[] = ['label' => $city->city_name, 'url' => null];

        return $breadcrumbs;
    }

    /** @return list<array{label:string,url:?string}> */
    private function restaurantBreadcrumbs(Restaurant $restaurant): array
    {
        $breadcrumbs = [
            ['label' => 'Accueil', 'url' => route('home')],
            ['label' => 'Restaurants', 'url' => route('restaurants.index')],
        ];

        $city = $this->cities->cityForCode((string) $restaurant->city_code);

        if ($city !== null) {
            $breadcrumbs[] = ['label' => $city->region['name'], 'url' => route('cities.show', $city->region['slug'])];

            $department = $this->geography->departments()->firstWhere('code', $city->department['code']);
            if ($department !== null && $department->slug !== $city->slug && $department->slug !== $city->region['slug']) {
                $breadcrumbs[] = ['label' => $department->name, 'url' => route('cities.show', $department->slug)];
            }

            $breadcrumbs[] = ['label' => $city->city_name, 'url' => route('cities.show', $city->slug)];
        }

        $breadcrumbs[] = ['label' => $restaurant->name, 'url' => null];

        return $breadcrumbs;
    }

    /** @return list<array{label:string,url:?string}> */
    private function departmentBreadcrumbs(object $department): array
    {
        $breadcrumbs = [
            ['label' => 'Accueil', 'url' => route('home')],
            ['label' => 'Restaurants', 'url' => route('restaurants.index')],
        ];

        if ($department->region['slug'] !== $department->slug) {
            $breadcrumbs[] = ['label' => $department->region['name'], 'url' => route('cities.show', $department->region['slug'])];
        }

        $breadcrumbs[] = ['label' => $department->name, 'url' => null];

        return $breadcrumbs;
    }

    private function cityLabel(object $city): string
    {
        return $city->city_name.($city->is_ambiguous ? ' — '.$city->department['name'] : '');
    }
}
