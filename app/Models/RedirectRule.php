<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class RedirectRule extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['is_active' => 'boolean', 'preserve_query' => 'boolean', 'last_hit_at' => 'datetime']; }
    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('redirect-rules-v1'));
        static::deleted(fn () => Cache::forget('redirect-rules-v1'));
    }
}
