<?php

namespace App\Models;

use App\Services\CityServiceSeoService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CityServiceSeoPage extends Model
{
    protected $guarded = [];

    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class);
    }

    protected static function booted(): void
    {
        static::saved(fn (): mixed => app(CityServiceSeoService::class)->forget());
        static::deleted(fn (): mixed => app(CityServiceSeoService::class)->forget());
    }
}
