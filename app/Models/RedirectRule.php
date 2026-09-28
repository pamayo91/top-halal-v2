<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class RedirectRule extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['is_active' => 'boolean', 'preserve_query' => 'boolean', 'last_hit_at' => 'datetime']; }
    protected static function booted(): void
    {
        static::saving(function (self $rule): void {
            $terminal = in_array((int) $rule->status_code, [404, 410], true);
            if ($terminal) {
                $rule->destination = null;
                $rule->preserve_query = false;
            } elseif (blank($rule->destination)) {
                throw ValidationException::withMessages(['destination' => 'Une destination est obligatoire pour une règle 301/302/307/308.']);
            }
        });
        static::saved(fn () => Cache::forget('redirect-rules-v1'));
        static::deleted(fn () => Cache::forget('redirect-rules-v1'));
    }
}
