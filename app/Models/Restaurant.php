<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Services\CityPageResolver;
use App\Services\CitySeoService;
use App\Services\GeographicPageResolver;

class Restaurant extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::saved(function (self $restaurant): void {
            if ($restaurant->wasRecentlyCreated || $restaurant->wasChanged(['city_name', 'city_code', 'status'])) {
                app(CityPageResolver::class)->forget();
                app(CitySeoService::class)->forget();
                app(GeographicPageResolver::class)->forget();
            }
            if ($restaurant->wasChanged('status')) {
                $restaurant->activateSubmittedOwner();

                if ($restaurant->status === 'published') {
                    app(\App\Services\RestaurantSubmissionMailer::class)->published($restaurant);
                }
            }
        });
        static::deleted(function (): void { app(CityPageResolver::class)->forget(); app(CitySeoService::class)->forget(); app(GeographicPageResolver::class)->forget(); });
        static::restored(function (): void { app(CityPageResolver::class)->forget(); app(CitySeoService::class)->forget(); app(GeographicPageResolver::class)->forget(); });
    }

    protected function casts(): array
    {
        return [
            'is_claimed' => 'boolean',
            'has_halal_meat' => 'boolean',
            'has_halal_chicken' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'manually_verified_at' => 'datetime',
            'legacy_published_at' => 'datetime',
            'legacy_modified_at' => 'datetime',
            'seo_unavailable_after' => 'datetime',
        ];
    }

    public function categories(): BelongsToMany { return $this->belongsToMany(Category::class, 'restaurant_category'); }
    public function features(): BelongsToMany { return $this->belongsToMany(Feature::class, 'restaurant_feature'); }
    public function openingHours(): HasMany { return $this->hasMany(RestaurantOpeningHour::class); }
    public function media(): HasMany { return $this->hasMany(RestaurantMedia::class)->orderBy('sort_order'); }
    public function reviews(): HasMany { return $this->hasMany(RestaurantReview::class); }
    public function claims(): HasMany { return $this->hasMany(RestaurantClaim::class); }
    public function legacyAuthorships(): HasMany { return $this->hasMany(LegacyRestaurantAuthorship::class); }
    public function outboundLinks(): HasMany { return $this->hasMany(RestaurantOutboundLink::class); }
    public function webEnrichment(): HasOne { return $this->hasOne(RestaurantWebEnrichment::class); }
    public function submission(): HasOne { return $this->hasOne(RestaurantSubmission::class); }
    public function removalRequests(): HasMany { return $this->hasMany(RestaurantRemovalRequest::class); }
    /** A single central rule for public claim presentation and write access. */
    public function isClaimable(): bool
    {
        return ! $this->claims()->whereIn('status', ['pending_email_verification', 'pending', 'approved', 'pending_publication', 'pending_activation'])->exists();
    }
    public function activateSubmittedOwner(): void
    {
        if ($this->status !== 'published') return;
        $submission = $this->submission;
        if (! $submission?->user_id || $submission->submitter_role !== 'owner') return;
        $claim = $this->claims()->where('user_id', $submission->user_id)->where('source', 'new_submission')->first();
        if (! $claim || $claim->status !== 'pending_publication') return;
        $claim->update(['status'=>'pending']);
        app(\App\Services\ClaimModeration::class)->approve($claim->fresh());
    }
    public function approvedReviewAggregate(): array
    {
        $aggregate = $this->reviews()->where('status', 'approved')->selectRaw('count(*) as count, avg(rating) as average')->first();
        return ['count' => (int) ($aggregate->count ?? 0), 'average' => $aggregate->average === null ? null : round((float) $aggregate->average, 2)];
    }
}
