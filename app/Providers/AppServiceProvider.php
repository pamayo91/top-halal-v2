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
use Illuminate\Support\Facades\Event;
use App\Models\EmailDeliveryLog;
use App\Services\EmailDeliveryErrorSanitizer;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
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
        RateLimiter::for('restaurant-duplicate-check', fn (Request $request): Limit => Limit::perMinute(30)->by('restaurant-duplicates|'.$request->ip()));
        RateLimiter::for('restaurant-submission', fn (Request $request): Limit => Limit::perHour(5)->by('restaurant|'.strtolower((string) $request->input('email')).'|'.$request->ip()));
        RateLimiter::for('contact', fn (Request $request): Limit => Limit::perHour(5)->by('contact|'.strtolower((string) $request->input('email')).'|'.$request->ip()));
        Event::listen(MessageSent::class, function (MessageSent $event): void {
            $id = $event->message->getHeaders()->get('X-Top-Halal-Email-Log')?->getBodyAsString();
            if (! $id) return;
            EmailDeliveryLog::whereKey($id)->whereIn('status', [EmailDeliveryLog::STATUS_QUEUED, EmailDeliveryLog::STATUS_PROCESSING])->update([
                'status' => EmailDeliveryLog::STATUS_SENT,
                'sent_at' => now(),
                'message_id' => method_exists($event->sent, 'getMessageId') ? $event->sent->getMessageId() : null,
                'error_message' => null,
            ]);
        });
        Event::listen(JobExceptionOccurred::class, function (JobExceptionOccurred $event): void {
            $id = $this->emailLogIdFromPayload($event->job->payload());
            if ($id) EmailDeliveryLog::whereKey($id)->where('status', EmailDeliveryLog::STATUS_PROCESSING)->update([
                'status' => EmailDeliveryLog::STATUS_QUEUED,
                'error_message' => EmailDeliveryErrorSanitizer::message($event->exception),
            ]);
        });
        Event::listen(JobFailed::class, function (JobFailed $event): void {
            $id = $this->emailLogIdFromPayload($event->job->payload());
            if ($id) EmailDeliveryLog::whereKey($id)->whereNotIn('status', [EmailDeliveryLog::STATUS_SENT, EmailDeliveryLog::STATUS_CANCELLED, EmailDeliveryLog::STATUS_EXPIRED])->update([
                'status' => EmailDeliveryLog::STATUS_FAILED,
                'failed_job_uuid' => $event->job->uuid(),
                'error_message' => EmailDeliveryErrorSanitizer::message($event->exception),
            ]);
        });
    }

    private function emailLogIdFromPayload(array $payload): ?int
    {
        $command = $payload['data']['command'] ?? null;
        return is_string($command) && preg_match('/logId";i:(\d+);/', $command, $matches) ? (int) $matches[1] : null;
    }
}
