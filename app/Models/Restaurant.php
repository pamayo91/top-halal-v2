<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Restaurant extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_claimed' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'legacy_published_at' => 'datetime',
            'legacy_modified_at' => 'datetime',
        ];
    }

    public function categories(): BelongsToMany { return $this->belongsToMany(Category::class, 'restaurant_category'); }
    public function features(): BelongsToMany { return $this->belongsToMany(Feature::class, 'restaurant_feature'); }
    public function locations(): BelongsToMany { return $this->belongsToMany(Location::class, 'restaurant_location'); }
    public function openingHours(): HasMany { return $this->hasMany(RestaurantOpeningHour::class); }
    public function media(): HasMany { return $this->hasMany(RestaurantMedia::class); }
    public function reviews(): HasMany { return $this->hasMany(RestaurantReview::class); }
    public function claims(): HasMany { return $this->hasMany(RestaurantClaim::class); }
    public function approvedReviewAggregate(): array
    {
        $aggregate = $this->reviews()->where('status', 'approved')->selectRaw('count(*) as count, avg(rating) as average')->first();
        return ['count' => (int) ($aggregate->count ?? 0), 'average' => $aggregate->average === null ? null : round((float) $aggregate->average, 2)];
    }
}
