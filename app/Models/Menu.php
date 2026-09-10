<?php

namespace App\Models;

use App\Services\PublicNavigation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Menu extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['is_active' => 'boolean']; }

    protected static function booted(): void
    {
        static::saved(fn () => app(PublicNavigation::class)->forget());
        static::deleted(fn () => app(PublicNavigation::class)->forget());
    }

    public function items(): HasMany { return $this->hasMany(MenuItem::class)->orderBy('sort_order'); }
    public function rootItems(): HasMany { return $this->items()->whereNull('parent_id'); }
}
