<?php

namespace App\Providers;

use App\Services\Geocoding\GeoPlateformeProvider;
use App\Services\Geocoding\GeocodingService;
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
    }
}
