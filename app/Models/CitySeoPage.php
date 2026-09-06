<?php
namespace App\Models;
use App\Services\CitySeoService;
use Illuminate\Database\Eloquent\Model;
class CitySeoPage extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::saved(fn (): mixed => app(CitySeoService::class)->forget());
        static::deleted(fn (): mixed => app(CitySeoService::class)->forget());
    }
}
