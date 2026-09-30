<?php

namespace App\Models;

use App\Services\EditorialSlugRedirects;
use App\Services\EditorialMediaAttachments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Article extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::saving(fn (self $article) => app(EditorialSlugRedirects::class)->assertCanSave($article));
        static::saved(fn (self $article) => app(EditorialSlugRedirects::class)->createForChangedSlug($article));
        static::saved(fn (self $article) => app(EditorialMediaAttachments::class)->sync($article, 'post'));
    }

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'legacy_published_at' => 'datetime', 'legacy_modified_at' => 'datetime', 'comments_enabled' => 'boolean', 'editorial_sidebar_enabled' => 'boolean', 'editorial_sidebar_overrides' => 'array'];
    }

    public function categories() { return $this->belongsToMany(EditorialCategory::class, 'article_category'); }
    public function tags() { return $this->belongsToMany(EditorialTag::class, 'article_tag'); }
    public function comments(): HasMany { return $this->hasMany(Comment::class); }
    public function featuredMedia(): HasOne { return $this->hasOne(ContentMedia::class, 'content_id')->where('content_type', 'post')->where('role', 'featured'); }
    public function contentMedia(): HasMany { return $this->hasMany(ContentMedia::class, 'content_id')->where('content_type', 'post')->orderBy('id'); }
}
