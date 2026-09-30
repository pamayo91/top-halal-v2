<?php

namespace App\Models;

use App\Services\EditorialSlugRedirects;
use App\Services\EditorialMediaAttachments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Page extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::saving(fn (self $page) => app(EditorialSlugRedirects::class)->assertCanSave($page));
        static::saved(fn (self $page) => app(EditorialSlugRedirects::class)->createForChangedSlug($page));
        static::saved(fn (self $page) => app(EditorialMediaAttachments::class)->sync($page, 'page'));
    }

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'legacy_published_at' => 'datetime', 'legacy_modified_at' => 'datetime', 'comments_enabled' => 'boolean', 'editorial_sidebar_enabled' => 'boolean', 'editorial_sidebar_overrides' => 'array'];
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }
}
