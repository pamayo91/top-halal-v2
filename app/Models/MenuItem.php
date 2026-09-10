<?php

namespace App\Models;

use App\Services\PublicNavigation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Validation\ValidationException;

class MenuItem extends Model
{
    public const LINK_TYPES = [
        'none' => 'Aucun lien', 'internal_url' => 'URL interne personnalisée', 'external_url' => 'URL externe',
        'page' => 'Page', 'article' => 'Article', 'city' => 'Ville', 'category' => 'Spécialité / Cuisine', 'feature' => 'Service',
    ];

    protected $guarded = [];
    protected function casts(): array { return ['target_blank' => 'boolean', 'nofollow' => 'boolean', 'visible_desktop' => 'boolean', 'visible_mobile' => 'boolean', 'is_active' => 'boolean']; }

    protected static function booted(): void
    {
        static::saving(function (self $item): void {
            $item->linkable_type = self::linkableClassFor($item->link_type);
            if ($item->link_type !== 'city') { $item->destination_key = null; }
            if (! in_array($item->link_type, ['internal_url', 'external_url'], true)) { $item->url = null; }
            if ($item->link_type === 'internal_url' && ! self::validInternalUrl((string) $item->url)) {
                throw ValidationException::withMessages(['url' => 'Une URL interne doit commencer par / et ne peut pas contenir de protocole.']);
            }
            if ($item->link_type === 'external_url' && ! self::validExternalUrl((string) $item->url)) {
                throw ValidationException::withMessages(['url' => 'Une URL externe doit utiliser http:// ou https://.']);
            }
        });
        static::saved(fn () => app(PublicNavigation::class)->forget());
        static::deleted(fn () => app(PublicNavigation::class)->forget());
    }

    public static function linkableClassFor(?string $type): ?string
    {
        return match ($type) {
            'page' => Page::class, 'article' => Article::class, 'category' => Category::class, 'feature' => Feature::class, default => null,
        };
    }

    private static function validInternalUrl(string $url): bool { return str_starts_with($url, '/') && ! str_starts_with($url, '//') && ! str_contains($url, '\\') && ! preg_match('/[\x00-\x1F]/', $url); }
    private static function validExternalUrl(string $url): bool { $parts = parse_url($url); return filter_var($url, FILTER_VALIDATE_URL) !== false && in_array($parts['scheme'] ?? null, ['http', 'https'], true); }

    public function menu(): BelongsTo { return $this->belongsTo(Menu::class); }
    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order'); }
    public function linkable(): MorphTo { return $this->morphTo(); }
}
