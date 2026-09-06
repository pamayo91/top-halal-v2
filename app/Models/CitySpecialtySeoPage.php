<?php

namespace App\Models;

use App\Services\CitySpecialtySeoService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CitySpecialtySeoPage extends Model
{
    protected $guarded = [];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    protected static function booted(): void
    {
        static::saved(fn (): mixed => app(CitySpecialtySeoService::class)->forget());
        static::deleted(fn (): mixed => app(CitySpecialtySeoService::class)->forget());
    }
}
