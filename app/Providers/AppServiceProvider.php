<?php

namespace App\Providers;

use App\Services\Geocoding\GeoPlateformeProvider;
use App\Services\Geocoding\GeocodingService;
use App\Services\WebEnrichment\GooglePlacesRestaurantWebSourceProvider;
use App\Services\WebEnrichment\RestaurantWebSourceProvider;
use App\Services\WebEnrichment\UnavailableRestaurantWebSourceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(GeocodingService::class, GeoPlateformeProvider::class);
        $this->app->bind(RestaurantWebSourceProvider::class, fn () => config('services.restaurant_web.provider') === 'google_places' && filled(config('services.restaurant_web.google_places_key')) ? new GooglePlacesRestaurantWebSourceProvider : new UnavailableRestaurantWebSourceProvider);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::defaultView('pagination.public');
        Paginator::defaultSimpleView('pagination.public');

        RateLimiter::for('authentication', function (Request $request): Limit {
            return Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip());
        });
        RateLimiter::for('address-autocomplete', fn (Request $request): Limit => Limit::perMinute(30)->by(($request->user()?->id ?? 'guest').'|'.$request->ip()));
        RateLimiter::for('public-address-autocomplete', fn (Request $request): Limit => Limit::perMinute(20)->by('address|'.$request->ip()));
        RateLimiter::for('restaurant-submission', fn (Request $request): Limit => Limit::perHour(5)->by('restaurant|'.strtolower((string) $request->input('email')).'|'.$request->ip()));
    }
}
